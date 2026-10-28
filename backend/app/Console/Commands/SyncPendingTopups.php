<?php

namespace App\Console\Commands;

use App\Models\Topup_request_tb;
use App\Services\TopupSettlementService;
use Illuminate\Console\Command;
use Throwable;

class SyncPendingTopups extends Command
{
    protected $signature = 'topups:sync-pending';

    protected $description = 'Verify pending payments with Opn even after the payment dialog is closed';

    public function handle(TopupSettlementService $settlement): int
    {
        $failures = 0;
        Topup_request_tb::where('Status', 'pending')
            ->whereNotNull('Provider_charge_id')
            ->orderBy('updated_at')->orderBy('Topup_id')
            ->limit(100)->get()->each(function ($topup) use ($settlement, &$failures) {
                try {
                    $result = $settlement->sync($topup);
                    $this->line("Topup {$topup->Topup_id}: {$result->Status}");
                } catch (Throwable $error) {
                    $failures++;
                    report($error);
                    $this->warn("Topup {$topup->Topup_id}: verification deferred; will retry.");
                } finally {
                    // Rotate pending rows fairly, including provider/network failures.
                    $topup->touch();
                }
            });

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
