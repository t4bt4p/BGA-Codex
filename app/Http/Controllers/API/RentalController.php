<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{Boardgame_tb,Rental_tb,Topup_request_tb,Transaction_tb,User};
use App\Services\BlockchainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RentalController extends Controller {
 public function __construct(protected BlockchainService $blockchain) {}
 public function rentGame(Request $r) {
  $r->validate(['Bg_id'=>'required|exists:Boardgame_tb,Bg_id','User_id'=>'nullable|exists:User_tb,User_id']);
  $u = $r->user();
  if ($u->User_role === 'admin' && $r->filled('User_id')) $u = User::with('wallet')->findOrFail($r->User_id);
  return DB::transaction(function() use($r,$u) {
   $g=Boardgame_tb::lockForUpdate()->findOrFail($r->Bg_id); if((int)$g->Bg_use_status!==1) abort(409,'บอร์ดเกมนี้ไม่ว่าง');
   $b=$this->blockchain->getRealBalance($u->User_id); if($b<$g->Bg_cost) abort(422,'ยอดโทเคนไม่เพียงพอ');
   $rent=Rental_tb::create(['User_id'=>$u->User_id,'Bg_id'=>$g->Bg_id,'Rental_cost'=>$g->Bg_cost,'rented_at'=>now(),'due_at'=>now()->addDays(7)]);
   $this->record($u,$g,$g->Bg_cost,'rental_debit',['rental_id'=>$rent->Rental_id]); $u->wallet->update(['Wallet_count'=>$b-$g->Bg_cost]); $g->update(['Bg_use_status'=>0]);
   return response()->json(['status'=>'success','rental'=>$rent->load('boardgame'),'balance'=>$b-$g->Bg_cost],201);
  });
 }
 public function returnGame(Request $r) {
  $r->validate(['rental_id'=>'nullable|exists:Rental_tb,Rental_id','User_id'=>'nullable|exists:User_tb,User_id','Bg_id'=>'nullable|exists:Boardgame_tb,Bg_id','late_fee'=>'nullable|integer|min:0']);
  abort_unless($r->filled('rental_id') || ($r->filled('User_id') && $r->filled('Bg_id')), 422, 'ต้องระบุ rental_id หรือ User_id กับ Bg_id');
  $actor = $r->user();
  DB::transaction(function() use($r,$actor) {
   $query = Rental_tb::with('boardgame')->lockForUpdate();
   $rent = $r->filled('rental_id') ? $query->findOrFail($r->rental_id) : $query->where('User_id',$r->User_id)->where('Bg_id',$r->Bg_id)->where('Rental_status','active')->firstOrFail();
   if($rent->User_id != $actor->User_id && $actor->User_role!=='admin') abort(403);
   if($rent->Rental_status!=='active') abort(409,'รายการนี้ถูกคืนแล้ว');
   $fee=$actor->User_role==='admin' ? (int)$r->input('late_fee',0) : 0;
   $u=User::with('wallet')->findOrFail($rent->User_id); $b=$this->blockchain->getRealBalance($u->User_id); if($fee>$b) abort(422,'ยอดโทเคนไม่พอ');
   $rent->update(['returned_at'=>now(),'late_fee'=>$fee,'Rental_status'=>'returned']); $rent->boardgame->update(['Bg_use_status'=>1]);
   if($fee){$this->record($u,$rent->boardgame,$fee,'late_fee_debit',['rental_id'=>$rent->Rental_id]);$u->wallet->update(['Wallet_count'=>$b-$fee]);}
  });
  return response()->json(['status'=>'success']);
 }
 public function myActiveRentals(Request $r) { return Rental_tb::with('boardgame')->where('User_id',$r->user()->User_id)->where('Rental_status','active')->latest('rented_at')->get(); }
 public function createTopupRequest(Request $r) {
  $r->validate(['amount'=>'required|integer|in:20,50,100,500,1000']);
  abort_unless(config('services.opn.secret_key'), 503, 'ยังไม่ได้ตั้งค่า Opn secret key');
  $reference = strtoupper(Str::random(12));
  $topup = Topup_request_tb::create(['User_id'=>$r->user()->User_id,'Amount'=>$r->amount,'Reference'=>$reference]);
  $charge = Http::withBasicAuth(config('services.opn.secret_key'),'')->asForm()->post('https://api.omise.co/charges', [
   'amount' => (int)$r->amount * 100,
   'currency' => 'THB',
   'source[type]' => 'promptpay',
   'metadata[reference]' => $reference,
  ]);
  if (!$charge->successful()) { $topup->delete(); return response()->json(['message'=>'ไม่สามารถสร้างรายการชำระเงินกับ Opn ได้','provider'=>$charge->json()],422); }
  $data = $charge->json();
  $topup->update(['Provider_charge_id'=>$data['id'] ?? null]);
  $downloadUri = $data['source']['scannable_code']['image']['download_uri'] ?? null;
  if ($downloadUri) {
   $image = Http::withBasicAuth(config('services.opn.secret_key'),'')->get($downloadUri);
   if ($image->successful()) {
    $data['qr_data'] = 'data:'.($image->header('Content-Type') ?: 'image/png').';base64,'.base64_encode($image->body());
   }
  }
  return response()->json(['status'=>'success','topup'=>$topup->fresh(),'charge'=>$data],201);
 }
 public function topupRequests() { return Topup_request_tb::with('user')->latest()->get(); }
 public function approveTopup(Request $r, Topup_request_tb $topup) {
  DB::transaction(function()use($r,$topup){
   $topup=Topup_request_tb::whereKey($topup->Topup_id)->lockForUpdate()->firstOrFail();
   if($topup->Status!=='pending') abort(409,'คำขอถูกดำเนินการแล้ว');
   $u=User::with('wallet')->findOrFail($topup->User_id); $b=$this->blockchain->getRealBalance($u->User_id);
   $this->record($u,null,$topup->Amount,'topup_credit',['reference'=>$topup->Reference]);
   $u->wallet->update(['Wallet_count'=>$b+$topup->Amount]);
   $topup->update(['Status'=>'approved','approved_at'=>now(),'approved_by'=>$r->user()->User_id]);
  });
  return response()->json(['status'=>'success']);
 }
 private function record(User $u, ?Boardgame_tb $g, int $amount, string $type, array $extra=[]): void { $d=array_merge(['type'=>$type,'user_id'=>$u->User_id,'bg_id'=>$g?->Bg_id,'cost'=>$amount,'timestamp'=>now()->toIso8601String()],$extra);$this->blockchain->addTransaction($d);Transaction_tb::create(['User_id'=>$u->User_id,'Bg_id'=>$g?->Bg_id,'T_cost'=>$amount,'T_type'=>$type]); }
}
