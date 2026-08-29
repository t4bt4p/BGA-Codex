<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{Topup_request_tb,User,Transaction_tb};
use App\Services\BlockchainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OpnWebhookController extends Controller {
 public function handle(Request $request, BlockchainService $chain) {
  $event=$request->json()->all(); $charge=$event['data']??[];
  if(($event['key']??'')!=='charge.complete' || ($charge['status']??'')!=='successful') return response()->json(['received'=>true]);
  $id=$charge['id']??null; if(!$id) return response()->json(['received'=>true]);
  $verified=Http::withBasicAuth(config('services.opn.secret_key'),'')->get("https://api.omise.co/charges/{$id}");
  if(!$verified->successful() || $verified->json('status')!=='successful') return response()->json(['message'=>'charge verification failed'],422);
  $topup=Topup_request_tb::where('Provider_charge_id',$id)->where('Status','pending')->first();
  if(!$topup) return response()->json(['received'=>true]);
  $amount=(int)round(($charge['amount']??0)/100);
  if ($amount !== (int)$topup->Amount) return response()->json(['message'=>'ยอด Charge ไม่ตรงกับคำขอเติมเงิน'],422);
  DB::transaction(function()use($id,$chain,$amount){
   $topup=Topup_request_tb::where('Provider_charge_id',$id)->where('Status','pending')->lockForUpdate()->first();
   if (!$topup) return;
   $u=User::with('wallet')->findOrFail($topup->User_id);
   $chain->addTransaction(['type'=>'topup_credit','user_id'=>$u->User_id,'cost'=>$amount,'reference'=>$topup->Reference,'provider_charge_id'=>$id,'timestamp'=>now()->toIso8601String()]);
   Transaction_tb::create(['User_id'=>$u->User_id,'T_cost'=>$amount,'T_type'=>'topup_credit']);
   $u->wallet->increment('Wallet_count',$amount);
   $topup->update(['Status'=>'approved','approved_at'=>now()]);
  });
  return response()->json(['received'=>true]);
 }
}
