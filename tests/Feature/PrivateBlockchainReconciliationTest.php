<?php

namespace Tests\Feature;

use App\Models\Transaction_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use App\Services\BlockchainService;
use App\Services\PolygonAnchorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateBlockchainReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_modified_database_transaction_is_detected_and_cannot_overwrite_its_block(): void
    {
        $wallet = Wallet_tb::create(['Wallet_count' => 20]);
        $user = User::create([
            'User_username' => 'integrity-user', 'User_password' => Hash::make('secret12'),
            'User_name' => 'Integrity User', 'Wallet_id' => $wallet->Wallet_id,
            'User_status' => 1, 'User_role' => 'user',
        ]);
        $tx = Transaction_tb::create(['User_id' => $user->User_id, 'T_cost' => 20, 'T_type' => 'topup_credit']);
        $chain = app(BlockchainService::class);
        $chain->syncTransaction($tx);
        $original = Storage::get('blockchain/node_1_ledger.json');
        $tx->update(['T_cost' => 200]);
        $result = $chain->verifyChain();
        $this->assertFalse($result['success']);
        $this->assertSame([$tx->Ts_id], $result['mismatched_transaction_ids']);
        $this->artisan('blockchain:reconcile')->assertFailed();
        foreach ([fn () => $chain->syncTransaction($tx), fn () => $chain->reconcileTransactions()] as $operation) {
            try {
                $operation();
                $this->fail('Modified transactions must not be silently accepted.');
            } catch (\RuntimeException $e) {
                $this->assertSame($original, Storage::get('blockchain/node_1_ledger.json'));
            }
        }
        $tx->delete();
        $this->assertSame([$tx->Ts_id], $chain->verifyChain()['orphaned_transaction_ids']);
        $this->assertFalse($chain->verifyChain()['success']);
    }

    public function test_verify_finds_and_reconcile_recovers_only_the_missing_database_transaction(): void
    {
        $wallet = Wallet_tb::create(['Wallet_count' => 70]);
        $user = User::create([
            'User_username' => 'reconcile-user',
            'User_password' => Hash::make('secret12'),
            'User_name' => 'Reconcile User',
            'Wallet_id' => $wallet->Wallet_id,
            'User_status' => 1,
            'User_role' => 'user',
        ]);
        $blockchain = app(BlockchainService::class);

        // Existing ledgers predate transaction_id; match them one-to-one without rewriting hashes.
        $blockchain->addTransaction(['type' => 'topup_credit', 'user_id' => $user->User_id, 'bg_id' => null, 'cost' => 20]);
        Transaction_tb::create(['User_id' => $user->User_id, 'T_cost' => 20, 'T_type' => 'topup_credit']);
        $missing = Transaction_tb::create(['User_id' => $user->User_id, 'T_cost' => 50, 'T_type' => 'topup_credit']);

        $before = $blockchain->verifyChain();
        $this->assertTrue($before['consensus']);
        $this->assertFalse($before['database_complete']);
        $this->assertSame([$missing->Ts_id], $before['missing_transaction_ids']);

        $after = $blockchain->reconcileTransactions();
        $this->assertTrue($after['success']);
        $this->assertSame(2, $after['transaction_block_count']);
        $this->assertSame($missing->Ts_id, $after['latest_block']['data']['transaction_id']);
        $this->assertSame(
            '0x'.$after['latest_block']['hash'],
            app(PolygonAnchorService::class)->digest($missing)
        );

        $blockchain->syncTransaction($missing);
        $this->assertSame(2, $blockchain->verifyChain()['transaction_block_count'], 'Retry must not append a duplicate block.');
    }
}
