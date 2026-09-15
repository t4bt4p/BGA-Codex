<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Events\BoardgameStatusChanged;
use App\Models\Boardgame_tb;
use App\Models\Rental_tb;
use App\Models\Topup_request_tb;
use App\Models\Transaction_tb;
use App\Models\User;
use App\Services\TopupSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RentalController extends Controller
{
    public function rentGame(Request $r)
    {
        $r->validate([
            'Bg_id' => 'required|exists:Boardgame_tb,Bg_id',
            'User_id' => 'nullable|exists:User_tb,User_id',
            'rental_days' => 'nullable|integer|min:1|max:7',
        ]);
        $u = $r->user();
        if ($u->User_role === 'admin' && $r->filled('User_id')) {
            $u = User::with('wallet')->findOrFail($r->User_id);
        }

        app(\App\Services\OverdueAccountService::class)->suspendIfOverdue($u);
        abort_if((int) $u->User_status !== 1, 403, 'บัญชีนี้ถูกระงับ ไม่สามารถเช่าบอร์ดเกมได้');

        return DB::transaction(function () use ($r, $u) {
            $g = Boardgame_tb::lockForUpdate()->findOrFail($r->Bg_id);
            if ((int) $g->Bg_use_status !== 1) {
                abort(409, 'บอร์ดเกมนี้ไม่ว่าง');
            }
            $wallet = $u->wallet()->lockForUpdate()->firstOrFail();
            $b = (int) $wallet->Wallet_count;
            $days = (int) $r->input('rental_days', 1);
            $dailyRate = (int) $g->Bg_cost;
            $totalCost = $dailyRate * $days;
            if ($b < $totalCost) {
                abort(422, 'ยอดโทเคนไม่เพียงพอ');
            }
            $rentedAt = now();
            $rent = Rental_tb::create([
                'User_id' => $u->User_id,
                'Bg_id' => $g->Bg_id,
                'Rental_cost' => $totalCost,
                'rental_days' => $days,
                'daily_rate' => $dailyRate,
                'rented_at' => $rentedAt,
                'due_at' => $rentedAt->copy()->addDays($days),
            ]);
            $this->record($u, $g, $totalCost, 'rental_debit', [
                'rental_id' => $rent->Rental_id,
                'rental_days' => $days,
                'daily_rate' => $dailyRate,
                'due_at' => $rent->due_at->toIso8601String(),
            ]);
            $wallet->update(['Wallet_count' => $b - $totalCost]);
            $g->update(['Bg_use_status' => 0]);
            DB::afterCommit(fn () => BoardgameStatusChanged::dispatch($g->fresh()));

            return response()->json(['status' => 'success', 'rental' => $rent->load('boardgame'), 'balance' => $b - $totalCost], 201);
        });
    }

    public function returnGame(Request $r)
    {
        $r->validate(['rental_id' => 'nullable|exists:Rental_tb,Rental_id', 'User_id' => 'nullable|exists:User_tb,User_id', 'Bg_id' => 'nullable|exists:Boardgame_tb,Bg_id']);
        abort_unless($r->filled('rental_id') || ($r->filled('User_id') && $r->filled('Bg_id')), 422, 'ต้องระบุ rental_id หรือ User_id กับ Bg_id');
        $actor = $r->user();
        $result = DB::transaction(function () use ($r, $actor) {
            $query = Rental_tb::with('boardgame')->lockForUpdate();
            $rent = $r->filled('rental_id') ? $query->findOrFail($r->rental_id) : $query->where('User_id', $r->User_id)->where('Bg_id', $r->Bg_id)->where('Rental_status', 'active')->firstOrFail();
            if ($rent->User_id != $actor->User_id && $actor->User_role !== 'admin') {
                abort(403);
            }
            if ($rent->Rental_status !== 'active') {
                abort(409, 'รายการนี้ถูกคืนแล้ว');
            }
            $u = User::findOrFail($rent->User_id);
            $isAdminForceReturn = $actor->User_role === 'admin';
            $rent->forceFill([
                'returned_at' => now(), 'Rental_status' => 'returned',
                'returned_by_user_id' => $actor->User_id,
                'returned_by_name' => $actor->User_role === 'admin' ? 'Admin' : $actor->User_name,
            ])->save();
            $rent->boardgame->update(['Bg_use_status' => 1]);
            DB::afterCommit(fn () => BoardgameStatusChanged::dispatch($rent->boardgame->fresh()));
            $this->record($u, $rent->boardgame, 0, 'return_event', ['rental_id' => $rent->Rental_id]);

            return [
                'forced_by_admin' => $isAdminForceReturn,
            ];
        });

        return response()->json(['status' => 'success'] + $result);
    }

    public function myActiveRentals(Request $r)
    {
        return Rental_tb::with('boardgame')->where('User_id', $r->user()->User_id)->where('Rental_status', 'active')->latest('rented_at')->get();
    }

    public function allRentals()
    {
        return Rental_tb::with(['user', 'boardgame'])->latest('rented_at')->get();
    }

    public function createTopupRequest(Request $r)
    {
        $r->validate(['amount' => 'required|integer|in:20,50,100,500,1000']);
        abort_unless(config('services.opn.secret_key'), 503, 'ยังไม่ได้ตั้งค่า Opn secret key');
        $reference = strtoupper(Str::random(12));
        $topup = Topup_request_tb::create(['User_id' => $r->user()->User_id, 'Amount' => $r->amount, 'Reference' => $reference]);
        $charge = Http::withBasicAuth(config('services.opn.secret_key'), '')
            ->connectTimeout(5)
            ->timeout(20)
            ->asForm()->post('https://api.omise.co/charges', [
            'amount' => (int) $r->amount * 100,
            'currency' => 'THB',
            'source[type]' => 'promptpay',
            'metadata[reference]' => $reference,
        ]);
        if (! $charge->successful()) {
            $topup->delete();

            return response()->json(['message' => 'ไม่สามารถสร้างรายการชำระเงินกับ Opn ได้', 'provider' => $charge->json()], 422);
        }
        $data = $charge->json();
        $topup->update(['Provider_charge_id' => $data['id'] ?? null]);
        $downloadUri = $data['source']['scannable_code']['image']['download_uri'] ?? null;
        if ($downloadUri) {
            // QR image retrieval must not leave the charge request hanging forever.
            $image = Http::withBasicAuth(config('services.opn.secret_key'), '')
                ->connectTimeout(1)
                ->timeout(2)
                ->get($downloadUri);
            if ($image->successful()) {
                $data['qr_data'] = 'data:'.($image->header('Content-Type') ?: 'image/png').';base64,'.base64_encode($image->body());
            } else {
                // The provider URL is already a usable QR image fallback.
                $data['qr_data'] = $downloadUri;
            }
        } elseif (! empty($data['source']['scannable_code']['image']['download_uri'])) {
            $data['qr_data'] = $data['source']['scannable_code']['image']['download_uri'];
        }

        return response()->json(['status' => 'success', 'topup' => $topup->fresh(), 'charge' => $data], 201);
    }

    public function topupRequests()
    {
        return Topup_request_tb::with('user')->latest()->get();
    }

    public function syncTopup(Request $request, Topup_request_tb $topup, TopupSettlementService $settlement)
    {
        abort_unless((int) $topup->User_id === (int) $request->user()->User_id, 403);
        $topup = $settlement->sync($topup);

        return response()->json([
            'status' => $topup->Status,
            'topup' => $topup,
            'wallet' => $request->user()->wallet()->firstOrFail()->fresh(),
        ]);
    }

    private function record(User $u, ?Boardgame_tb $g, int $amount, string $type, array $extra = []): void
    {
        Transaction_tb::create([
            'User_id' => $u->User_id,
            'Bg_id' => $g?->Bg_id,
            'Rental_id' => $extra['rental_id'] ?? null,
            'T_cost' => $amount,
            'T_type' => $type,
        ]);
    }
}
