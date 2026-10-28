<?php

namespace Tests\Feature;

use App\Models\Boardgame_category_tb;
use App\Models\Boardgame_tb;
use App\Models\Rental_tb;
use App\Models\Transaction_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use App\Services\BlockchainService;
use App\Services\TransactionRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $other;
    private Transaction_tb $transaction;
    private string $node;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        foreach (['owner', 'other'] as $name) {
            $wallet = Wallet_tb::create(['Wallet_count' => 100]);
            $this->$name = User::create([
                'User_username' => $name, 'User_password' => 'unused', 'User_name' => $name,
                'User_role' => 'user', 'User_status' => 1, 'Wallet_id' => $wallet->Wallet_id,
            ]);
        }
        $this->transaction = Transaction_tb::create([
            'User_id' => $this->owner->User_id, 'T_type' => 'topup_credit', 'T_cost' => 100,
        ]);
        $block = app(BlockchainService::class)->syncTransaction($this->transaction);
        $this->transaction->update(['Chain_payload_hash' => '0x'.$block['hash'], 'Chain_status' => 'confirmed']);
        $this->node = Storage::get('blockchain/node_1_ledger.json');
        Queue::fake();
        Sanctum::actingAs($this->owner);
    }

    public function test_restores_all_node_backed_fields_without_rewriting_nodes_or_requeuing(): void
    {
        $original = (array) DB::table('Transaction_tb')->where('Ts_id', $this->transaction->Ts_id)->first();
        $category = Boardgame_category_tb::create(['Bg_category_name' => 'Recovery']);
        $game = Boardgame_tb::create([
            'Bg_name' => 'Recovery', 'Bg_cost' => 20, 'Bg_min_player' => 2, 'Bg_max_player' => 4,
            'Bg_playduration' => 60, 'Bg_Catetogory_id' => $category->Bg_category_id,
            'Bg_use_status' => 1, 'Bg_Image' => '',
        ]);
        $rental = Rental_tb::create([
            'User_id' => $this->other->User_id, 'Bg_id' => $game->Bg_id, 'Rental_cost' => 20,
            'rented_at' => now(), 'due_at' => now()->addDay(),
        ]);
        $tampered = [
            'T_cost' => 999, 'T_type' => 'rental_debit', 'User_id' => $this->other->User_id,
            'Bg_id' => $game->Bg_id, 'Rental_id' => $rental->Rental_id,
            'created_at' => '2000-01-01 01:02:03', 'Chain_payload_hash' => '0x'.str_repeat('a', 64),
        ];
        DB::table('Transaction_tb')->where('Ts_id', $this->transaction->Ts_id)->update($tampered);
        $result = app(TransactionRecoveryService::class)->restore();
        $this->assertCount(1, $result['changes']);
        $this->assertEqualsCanonicalizing(array_keys($tampered), array_keys($result['changes'][$this->transaction->Ts_id]['after']));
        $this->assertSame($original, (array) DB::table('Transaction_tb')->where('Ts_id', $this->transaction->Ts_id)->first());
        foreach ([1, 2, 3] as $n) {
            $this->assertSame($this->node, Storage::get("blockchain/node_{$n}_ledger.json"));
        }
        Queue::assertNothingPushed();
        $this->assertSame([], app(TransactionRecoveryService::class)->restore()['changes']);
    }

    public function test_history_repairs_ownership_before_applying_authorization_filter(): void
    {
        $this->transaction->update(['User_id' => $this->other->User_id, 'T_cost' => 999]);
        Sanctum::actingAs($this->other);
        $this->getJson('/api/transactions')->assertOk()->assertJsonCount(0)->assertHeader('X-Transactions-Restored', '1');
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/transactions')->assertOk()->assertJsonCount(1)->assertJsonPath('0.T_cost', 100);
    }

    public function test_financial_attempt_commits_repair_but_does_not_create_a_payment(): void
    {
        $this->transaction->update(['T_cost' => 999]);
        $this->postJson('/api/topups', ['amount' => 20])->assertConflict()->assertJsonPath('code', 'transaction_data_restored');
        $this->assertSame(100, (int) $this->transaction->fresh()->T_cost);
        $this->assertDatabaseCount('Topup_request_tb', 0);
        $this->assertSame(100, (int) $this->owner->wallet()->first()->Wallet_count);
    }

    public function test_dry_run_reports_changes_without_mutating_then_command_restores(): void
    {
        $this->transaction->update(['T_cost' => 999]);
        $this->artisan('transactions:restore --dry-run')->assertSuccessful();
        $this->assertSame(999, (int) $this->transaction->fresh()->T_cost);
        $this->artisan('transactions:restore')->assertSuccessful();
        $this->assertSame(100, (int) $this->transaction->fresh()->T_cost);
    }

    public function test_one_corrupt_copy_does_not_prevent_recovery_from_two_valid_copies(): void
    {
        $this->transaction->update(['T_cost' => 999]);
        Storage::put('blockchain/node_3_ledger.json', 'invalid');
        $this->artisan('transactions:restore')->assertSuccessful();
        $this->assertSame(100, (int) $this->transaction->fresh()->T_cost);
        $this->assertSame('invalid', Storage::get('blockchain/node_3_ledger.json'));
    }

    public function test_missing_two_copies_does_not_manufacture_a_majority_or_restore(): void
    {
        $this->transaction->update(['T_cost' => 999]);
        Storage::delete(['blockchain/node_2_ledger.json', 'blockchain/node_3_ledger.json']);
        $this->getJson('/api/transactions')->assertConflict();
        $this->assertSame(999, (int) $this->transaction->fresh()->T_cost);
        $this->assertFalse(Storage::exists('blockchain/node_2_ledger.json'));
        $this->assertFalse(Storage::exists('blockchain/node_3_ledger.json'));
    }

    public function test_invalid_hashes_in_two_copies_prevent_recovery(): void
    {
        $this->transaction->update(['T_cost' => 999]);
        $bad = json_decode($this->node, true);
        $bad[1]['data']['cost'] = 999;
        foreach ([1, 2] as $n) {
            Storage::put("blockchain/node_{$n}_ledger.json", json_encode($bad));
        }
        $this->getJson('/api/transactions')->assertConflict();
        $this->assertSame(999, (int) $this->transaction->fresh()->T_cost);
    }

    public function test_changed_primary_key_is_reported_without_inventing_or_deleting_rows(): void
    {
        $id = $this->transaction->Ts_id;
        DB::table('Transaction_tb')->where('Ts_id', $id)->update(['Ts_id' => 900]);
        $result = app(TransactionRecoveryService::class)->restore();
        $this->assertSame([$id], $result['missing_transaction_ids']);
        $this->assertDatabaseCount('Transaction_tb', 1);
        $this->assertDatabaseHas('Transaction_tb', ['Ts_id' => 900]);
        $this->assertDatabaseMissing('Transaction_tb', ['Ts_id' => $id]);
    }

    public function test_unanchored_rows_and_polygon_only_fields_are_not_guessed(): void
    {
        $pending = Transaction_tb::create(['User_id' => $this->owner->User_id, 'T_cost' => 20, 'T_type' => 'topup_credit']);
        $this->transaction->update(['Chain_tx_hash' => '0x'.str_repeat('b', 64), 'Chain_status' => 'failed']);
        $before = DB::table('Transaction_tb')->orderBy('Ts_id')->get()->toJson();
        $result = app(TransactionRecoveryService::class)->restore();
        $this->assertSame([], $result['changes']);
        $this->assertSame($before, DB::table('Transaction_tb')->orderBy('Ts_id')->get()->toJson());
        $this->assertNotNull($pending->fresh());
        Queue::assertPushed(\App\Jobs\RecordPrivateBlockchainTransaction::class, 1);
    }

    public function test_swapped_unique_payload_hashes_are_restored_atomically(): void
    {
        $second = Transaction_tb::create(['User_id' => $this->owner->User_id, 'T_cost' => 20, 'T_type' => 'topup_credit']);
        $block = app(BlockchainService::class)->syncTransaction($second);
        $firstHash = $this->transaction->fresh()->Chain_payload_hash;
        $secondHash = '0x'.$block['hash'];
        $this->transaction->update(['Chain_payload_hash' => null]);
        $second->update(['Chain_payload_hash' => $firstHash]);
        $this->transaction->update(['Chain_payload_hash' => $secondHash]);
        $result = app(TransactionRecoveryService::class)->restore();
        $this->assertCount(2, $result['changes']);
        $this->assertSame($firstHash, $this->transaction->fresh()->Chain_payload_hash);
        $this->assertSame($secondHash, $second->fresh()->Chain_payload_hash);
    }
}
