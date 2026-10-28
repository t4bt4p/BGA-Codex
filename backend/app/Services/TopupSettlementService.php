<?php

namespace App\Services;

use App\Models\Topup_request_tb;
use App\Models\Transaction_tb;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TopupSettlementService
{
    public function __construct(private readonly OpnConfiguration $opnConfiguration) {}

    public function sync(Topup_request_tb $topup): Topup_request_tb
    {
        if ($topup->Status === 'approved') {
            return $topup;
        }

        if (! $topup->Provider_charge_id) {
            throw new RuntimeException('รายการเติมเงินนี้ไม่มี Charge ID');
        }

        $charge = Http::withBasicAuth(config('services.opn.secret_key'), '')
            ->connectTimeout(10)
            ->timeout(25)
            ->get("https://api.omise.co/charges/{$topup->Provider_charge_id}");

        if (! $charge->successful() || $charge->json('id') !== $topup->Provider_charge_id) {
            throw new RuntimeException('ไม่สามารถยืนยัน Charge กับ Opn ได้');
        }

        try {
            $this->opnConfiguration->assertChargeMode($charge->json());
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['payment' => 'ไม่สามารถยืนยันโหมดของรายการชำระเงินได้ กรุณาติดต่อผู้ดูแล']);
        }

        if (in_array($charge->json('status'), ['failed', 'expired'], true)) {
            // Never overwrite a successful settlement from a concurrent request.
            Topup_request_tb::whereKey($topup->Topup_id)->whereNotIn('Status', ['approved', 'cancelled'])
                ->update(['Status' => 'failed']);

            return $topup->fresh();
        }

        if ($charge->json('status') !== 'successful' || $charge->json('paid') !== true) {
            return $topup->fresh();
        }

        $amountSatang = (int) $charge->json('amount');
        $currency = strtoupper((string) $charge->json('currency'));
        $reference = (string) $charge->json('metadata.reference');
        if ($currency !== 'THB' || $amountSatang !== (int) $topup->Amount * 100 || $reference !== (string) $topup->Reference) {
            throw ValidationException::withMessages(['payment' => 'ข้อมูลจำนวนเงิน สกุลเงิน หรือเลขอ้างอิงไม่ตรงกับคำขอเติมเงิน']);
        }

        return app(WalletIntegrityService::class)->transaction(function () use ($topup, $amountSatang) {
            $locked = Topup_request_tb::whereKey($topup->Topup_id)->lockForUpdate()->firstOrFail();
            if ($locked->Status === 'approved') {
                return $locked;
            }

            $amount = (int) ($amountSatang / 100);
            $user = User::findOrFail($locked->User_id);
            $wallet = $user->wallet()->lockForUpdate()->firstOrFail();
            $balance = app(WalletIntegrityService::class)->assertValid($user, $wallet);
            Transaction_tb::create([
                'User_id' => $user->User_id,
                'T_cost' => $amount,
                'T_type' => 'topup_credit',
            ]);
            $wallet->update(['Wallet_count' => $balance + $amount]);
            $locked->update(['Status' => 'approved', 'approved_at' => now()]);

            return $locked->fresh();
        });
    }

    public function syncByChargeId(string $chargeId): ?Topup_request_tb
    {
        $topup = Topup_request_tb::where('Provider_charge_id', $chargeId)->first();

        return $topup ? $this->sync($topup) : null;
    }
}
