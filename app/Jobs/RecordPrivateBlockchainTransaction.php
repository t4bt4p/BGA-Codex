<?php

namespace App\Jobs;

use App\Models\Transaction_tb;
use App\Services\BlockchainService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordPrivateBlockchainTransaction implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $transactionId) {}

    public function backoff(): array
    {
        return [5, 15, 60, 300];
    }

    public function handle(BlockchainService $blockchain): void
    {
        $transaction = Transaction_tb::findOrFail($this->transactionId);
        $blockchain->syncTransaction($transaction);

        // Polygon must anchor the resulting private block hash, so it is queued second.
        if (config('services.polygon.enabled')) {
            AnchorTransactionOnPolygon::dispatch($this->transactionId);
        }
    }
}
