<?php

namespace App\Console\Commands;

use App\Jobs\AnchorTransactionOnPolygon;
use App\Models\Transaction_tb;
use Illuminate\Console\Command;

class AnchorExistingTransactions extends Command
{
    protected $signature = 'polygon:anchor-existing {--limit=100 : Maximum transactions to queue}';

    protected $description = 'Queue existing unanchored transactions for Polygon Amoy';

    public function handle(): int
    {
        if (! config('services.polygon.enabled')) {
            $this->error('POLYGON_ENABLED is false.');

            return self::FAILURE;
        }

        $transactions = Transaction_tb::whereNull('Chain_tx_hash')
            ->where(fn ($query) => $query->whereNull('Chain_status')->orWhere('Chain_status', 'failed'))
            ->oldest('Ts_id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($transactions as $transaction) {
            $transaction->update([
                'Chain_network' => config('services.polygon.network'),
                'Chain_status' => 'pending',
                'Chain_error' => null,
            ]);
            AnchorTransactionOnPolygon::dispatch((int) $transaction->Ts_id);
        }

        $this->info("Queued {$transactions->count()} transaction(s).");

        return self::SUCCESS;
    }
}
