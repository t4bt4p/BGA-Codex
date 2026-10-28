<?php

namespace App\Console\Commands;

use App\Services\TransactionRecoveryService;
use Illuminate\Console\Command;

class RestoreTransactions extends Command
{
    protected $signature = 'transactions:restore {--dry-run : Inspect without changing database records}';

    protected $description = 'Restore supported transaction fields from a validated majority of local nodes';

    public function handle(TransactionRecoveryService $recovery): int
    {
        $result = $recovery->restore((bool) $this->option('dry-run'));
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $result['missing_transaction_ids'] ? self::FAILURE : self::SUCCESS;
    }
}
