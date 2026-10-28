<?php

namespace App\Services;

use App\Exceptions\WalletBalanceCorrected;
use App\Exceptions\WalletBalanceMismatch;
use App\Models\User;
use App\Models\Wallet_tb;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WalletIntegrityService
{
    public function __construct(private BlockchainService $blockchain) {}

    /** Roll back the attempted operation before committing a separate balance repair. */
    public function transaction(callable $operation): mixed
    {
        $recovery = app(TransactionRecoveryService::class)->restore();
        if ($recovery['changes']) {
            throw new \App\Exceptions\TransactionDataRestored();
        }
        try {
            return DB::transaction($operation);
        } catch (WalletBalanceMismatch $error) {
            $balance = DB::transaction(function () use ($error) {
                $user = User::findOrFail($error->userId);
                $wallet = $user->wallet()->lockForUpdate()->firstOrFail();
                // Revalidate after acquiring the lock; queued work may have advanced.
                $expected = $this->blockchain->verifiedWalletBalance($error->userId);
                $previous = (int) $wallet->Wallet_count;
                $wallet->update(['Wallet_count' => $expected]);
                Log::warning('Wallet balance restored from verified ledger', [
                    'user_id' => $user->User_id, 'wallet_id' => $wallet->Wallet_id,
                    'previous_balance' => $previous, 'corrected_balance' => $expected,
                ]);

                return $expected;
            });

            // Throw only after the repair commits, so HTTP 409 cannot undo it.
            throw new WalletBalanceCorrected($balance);
        }
    }

    /** Call with the wallet row locked inside the balance-changing transaction. */
    public function assertValid(User $user, Wallet_tb $wallet): int
    {
        $expected = $this->blockchain->verifiedWalletBalance((int) $user->User_id);
        if ((int) $wallet->Wallet_count !== $expected) {
            Log::warning('Wallet balance does not match ledger', [
                'user_id' => $user->User_id,
                'wallet_id' => $wallet->Wallet_id,
                'expected_balance' => $expected,
                'actual_balance' => (int) $wallet->Wallet_count,
            ]);
            throw new WalletBalanceMismatch((int) $user->User_id);
        }

        return $expected;
    }
}
