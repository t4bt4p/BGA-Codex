<?php

namespace App\Services;

use App\Models\Topup_request_tb;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Sandbox simulation only: PromptPay live charges cannot be cancelled this way. */
class TestTopupReplacementService
{
    public function __construct(private TopupSettlementService $settlement) {}

    public function create(User $user, callable $create): mixed
    {
        if (! config('services.opn.replace_pending_test_charges') || config('services.opn.live_mode')) {
            return $create();
        }
        abort_unless(str_starts_with((string) config('services.opn.secret_key'), 'skey_test_'), 503,
            'การแทนที่ QR ในโหมดทดสอบต้องใช้ Test Secret Key');

        // Covers cancellation AND creation, including requests from other tabs.
        $lock = Cache::lock('topup-create:'.$user->User_id, 900);
        abort_unless($lock->get(), 409, 'กำลังสร้างหรือเปลี่ยน QR กรุณารอสักครู่');
        try {
            $pending = $user->topupRequests()->where('Status', 'pending')
                ->whereNotNull('Provider_charge_id')->orderBy('Topup_id')->limit(10)->get();
            foreach ($pending as $topup) {
                $this->cancel($topup);
            }
            abort_if($user->topupRequests()->where('Status', 'pending')->whereNotNull('Provider_charge_id')->exists(),
                409, 'ยังมีรายการเก่าที่ต้องตรวจสอบ กรุณากดสร้าง QR อีกครั้ง');

            return $create();
        } catch (\Illuminate\Http\Client\ConnectionException $error) {
            report($error);
            abort(503, 'ยังติดต่อ Opn เพื่อเปลี่ยน QR ไม่สำเร็จ กรุณาลองอีกครั้ง');
        } finally {
            $lock->release();
        }
    }

    private function cancel(Topup_request_tb $topup): void
    {
        abort_unless(str_starts_with($topup->Provider_charge_id, 'chrg_test_'), 409,
            'ไม่สามารถยกเลิกรายการเงินจริงด้วยโหมดทดสอบ');
        // sync verifies the provider ID and livemode before any sandbox mutation.
        $current = $this->settlement->sync($topup);
        $this->stopIfPaid($current);
        if ($current->Status !== 'pending') {
            return;
        }

        Http::withBasicAuth(config('services.opn.secret_key'), '')
            ->connectTimeout(10)->timeout(25)
            ->post("https://api.omise.co/charges/{$current->Provider_charge_id}/mark_as_failed");

        // Re-read even on a provider rejection: payment may have won the race.
        $current = $this->settlement->sync($current);
        $this->stopIfPaid($current);
        abort_unless($current->Status === 'failed', 503,
            'ยังยืนยันการยกเลิก QR เก่าไม่ได้ จึงยังไม่สร้าง QR ใหม่ กรุณาลองอีกครั้ง');
        Topup_request_tb::whereKey($current->Topup_id)->where('Status', 'failed')
            ->update(['Status' => 'cancelled']);
    }

    private function stopIfPaid(Topup_request_tb $topup): void
    {
        abort_if($topup->Status === 'approved', 409,
            "รายการเดิม {$topup->Amount} บาทชำระสำเร็จและเติมโทเค่นแล้ว จึงหยุดสร้าง QR ใหม่ กรุณาตรวจสอบยอดคงเหลือ");
    }
}
