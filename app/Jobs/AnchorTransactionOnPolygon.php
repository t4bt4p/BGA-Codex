<?php

namespace App\Jobs;

use App\Models\Transaction_tb;
use App\Services\PolygonAnchorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AnchorTransactionOnPolygon implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $transactionId) {}

    public function backoff(): array
    {
        return [15, 60, 300, 900];
    }

    public function handle(PolygonAnchorService $polygon): void
    {
        $transaction = Transaction_tb::findOrFail($this->transactionId);
        // The on-chain digest is always the exact hash of this transaction's private block.
        $digest = $polygon->digest($transaction);
        if ($transaction->Chain_status === 'confirmed' && $transaction->Chain_payload_hash === $digest) {
            return;
        }

        $transaction->update([
            'Chain_network' => config('services.polygon.network'),
            'Chain_payload_hash' => $digest,
            'Chain_status' => 'processing',
            'Chain_error' => null,
        ]);

        try {
            $result = $polygon->anchor($transaction->fresh());
            $transaction->update([
                'Chain_tx_hash' => $result['transaction_hash'],
                'Chain_block_number' => $result['block_number'],
                'Chain_status' => 'confirmed',
                'Chain_confirmed_at' => now(),
            ]);
        } catch (Throwable $error) {
            $transaction->update([
                'Chain_status' => 'failed',
                'Chain_error' => mb_substr($error->getMessage(), 0, 2000),
            ]);
            throw $error;
        }
    }
}
