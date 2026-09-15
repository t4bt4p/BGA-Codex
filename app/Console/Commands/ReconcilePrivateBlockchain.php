<?php

namespace App\Console\Commands;

use App\Services\BlockchainService;
use Illuminate\Console\Command;

class ReconcilePrivateBlockchain extends Command
{
    protected $signature = 'blockchain:reconcile';

    protected $description = 'Append database transactions missing from the private blockchain';

    public function handle(BlockchainService $blockchain): int
    {
        $before = $blockchain->verifyChain();
        if (! $before['consensus'] || $before['mismatched_transaction_ids'] || $before['orphaned_transaction_ids']) {
            $this->error($before['message']);

            return self::FAILURE;
        }
        $missing = $before['missing_transaction_ids'];
        if ($missing === []) {
            $this->info('Private blockchain already contains every database transaction.');

            return self::SUCCESS;
        }

        $this->line('Recovering transaction IDs: '.implode(', ', $missing));
        $after = $blockchain->reconcileTransactions();
        $this->info("Reconciliation complete ({$after['transaction_block_count']} transaction blocks).");

        return $after['success'] ? self::SUCCESS : self::FAILURE;
    }
}
