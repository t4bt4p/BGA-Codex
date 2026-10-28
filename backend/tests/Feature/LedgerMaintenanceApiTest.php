<?php

namespace Tests\Feature;

use App\Models\Transaction_tb;
use App\Models\User;
use App\Services\BlockchainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LedgerMaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    private function signIn(string $role): User
    {
        $user = User::create(['User_username' => 'maintenance-'.$role, 'User_password' => 'unused', 'User_name' => 'Maintenance', 'User_role' => $role, 'User_status' => 1]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_only_admin_can_repair_or_reconcile(): void
    {
        foreach (['repair', 'reconcile'] as $action) {
            $this->postJson('/api/blockchain/'.$action)->assertUnauthorized();
        }
        $this->signIn('user');
        foreach (['repair', 'reconcile'] as $action) {
            $this->postJson('/api/blockchain/'.$action)->assertForbidden();
        }
        $this->assertFalse(Storage::exists('blockchain/node_1_ledger.json'));
    }

    public function test_admin_repairs_corrupted_copy_from_valid_majority(): void
    {
        $this->signIn('admin');
        app(BlockchainService::class);
        $original = Storage::get('blockchain/node_1_ledger.json');
        Storage::put('blockchain/node_3_ledger.json', '{}');
        $this->postJson('/api/blockchain/repair')->assertOk()->assertJsonPath('consensus', true)->assertJsonPath('success', true);
        $this->assertSame($original, Storage::get('blockchain/node_3_ledger.json'));
    }

    public function test_no_majority_returns_conflict_without_overwriting_copies(): void
    {
        $this->signIn('admin');
        app(BlockchainService::class);
        Storage::put('blockchain/node_2_ledger.json', '{}');
        Storage::put('blockchain/node_3_ledger.json', '[]');
        foreach (['repair', 'reconcile'] as $action) {
            $this->postJson('/api/blockchain/'.$action)->assertConflict()->assertJsonStructure(['message']);
        }
        $this->assertSame('{}', Storage::get('blockchain/node_2_ledger.json'));
        $this->assertSame('[]', Storage::get('blockchain/node_3_ledger.json'));
    }

    public function test_reconcile_adds_missing_transaction_once_and_rejects_mismatch(): void
    {
        $user = $this->signIn('admin');
        $chain = app(BlockchainService::class);
        $transaction = Transaction_tb::create(['User_id' => $user->User_id, 'T_cost' => 20, 'T_type' => 'topup_credit']);
        $this->postJson('/api/blockchain/reconcile')->assertOk()->assertJsonPath('success', true)->assertJsonPath('transaction_block_count', 1);
        $this->postJson('/api/blockchain/reconcile')->assertOk()->assertJsonPath('transaction_block_count', 1);
        $original = Storage::get('blockchain/node_1_ledger.json');
        $transaction->update(['T_cost' => 200]);
        $this->postJson('/api/blockchain/reconcile')->assertConflict()->assertJsonStructure(['message']);
        $this->assertSame($original, Storage::get('blockchain/node_1_ledger.json'));
        $this->assertSame([$transaction->Ts_id], $chain->verifyChain()['mismatched_transaction_ids']);
    }
}
