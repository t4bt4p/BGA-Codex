<?php

namespace Tests\Feature;

use App\Models\Boardgame_category_tb;
use App\Models\Boardgame_tb;
use App\Models\Topup_request_tb;
use App\Models\Transaction_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use App\Services\BlockchainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Transaction_tb $opening;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
        config(['services.opn.secret_key' => 'skey_test_example', 'services.opn.live_mode' => false]);
        $wallet = Wallet_tb::create(['Wallet_count' => 300]);
        $this->user = User::create([
            'User_username' => 'integrity', 'User_password' => bcrypt('secret12'),
            'User_name' => 'Integrity', 'User_role' => 'user', 'User_status' => 1,
            'Wallet_id' => $wallet->Wallet_id,
        ]);
        $this->opening = Transaction_tb::create([
            'User_id' => $this->user->User_id, 'T_type' => 'topup_credit', 'T_cost' => 300,
        ]);
        app(BlockchainService::class)->syncTransaction($this->opening);
        Sanctum::actingAs($this->user);
    }

    private function paidTopup(int $amount): Topup_request_tb
    {
        $reference = 'REF'.$amount;
        $topup = Topup_request_tb::create([
            'User_id' => $this->user->User_id, 'Amount' => $amount,
            'Reference' => $reference, 'Provider_charge_id' => 'chrg_'.$amount, 'Status' => 'pending',
        ]);
        Http::fake(['api.omise.co/charges/chrg_'.$amount => Http::response([
            'id' => 'chrg_'.$amount, 'status' => 'successful', 'paid' => true, 'livemode' => false,
            'amount' => $amount * 100, 'currency' => 'THB', 'metadata' => ['reference' => $reference],
        ])]);

        return $topup;
    }

    public function test_300_plus_60_plus_20_is_380_and_replays_do_not_double_credit(): void
    {
        foreach ([60 => 360, 20 => 380] as $amount => $expected) {
            $topup = $this->paidTopup($amount);
            $this->postJson("/api/topups/{$topup->Topup_id}/sync")->assertOk();
            $this->assertSame($expected, (int) $this->user->wallet()->first()->Wallet_count);
            app(BlockchainService::class)->syncTransaction(Transaction_tb::orderByDesc('Ts_id')->first());
            $this->postJson("/api/topups/{$topup->Topup_id}/sync")->assertOk();
            $this->assertSame($expected, app(BlockchainService::class)->verifiedWalletBalance($this->user->User_id));
        }
        $this->assertDatabaseCount('Transaction_tb', 3);
    }

    public function test_changed_wallet_is_repaired_without_creating_qr_or_rental_until_retry(): void
    {
        DB::table('Wallet_tb')->where('Wallet_id', $this->user->Wallet_id)->update(['Wallet_count' => 900]);
        $this->postJson('/api/topups', ['amount' => 20])->assertStatus(409)
            ->assertJsonPath('code', 'wallet_balance_corrected')->assertJsonPath('balance', 300);
        $this->assertSame(300, (int) $this->user->wallet()->first()->Wallet_count);
        Http::assertNothingSent();
        $this->user->wallet()->update(['Wallet_count' => 1]);
        $category = Boardgame_category_tb::create(['Bg_category_name' => 'Test']);
        $game = Boardgame_tb::create([
            'Bg_name' => 'Game', 'Bg_cost' => 20, 'Bg_min_player' => 2, 'Bg_max_player' => 4,
            'Bg_playduration' => 60, 'Bg_Catetogory_id' => $category->Bg_category_id,
            'Bg_use_status' => 1, 'Bg_Image' => '',
        ]);
        $this->postJson('/api/rentals/rent', ['Bg_id' => $game->Bg_id])->assertStatus(409)
            ->assertJsonPath('code', 'wallet_balance_corrected')->assertJsonPath('balance', 300);
        $this->assertSame(300, (int) $this->user->wallet()->first()->Wallet_count);
        $this->assertDatabaseCount('Rental_tb', 0);
        $this->assertDatabaseCount('Topup_request_tb', 0);
        $this->assertDatabaseCount('Transaction_tb', 1);
        $this->assertSame(1, (int) $game->fresh()->Bg_use_status);
        $this->assertSame(1, (int) $this->user->fresh()->User_status);
        $this->postJson('/api/rentals/rent', ['Bg_id' => $game->Bg_id])->assertCreated();
        $this->assertSame(280, (int) $this->user->wallet()->first()->Wallet_count);
    }

    public function test_paid_topup_repairs_balance_then_credits_original_payment_once_on_retry(): void
    {
        $topup = $this->paidTopup(20);
        $this->user->wallet()->update(['Wallet_count' => 999]);
        $this->postJson('/api/webhooks/opn', ['key' => 'charge.complete', 'data' => [
            'id' => 'chrg_20', 'status' => 'successful',
        ]])->assertStatus(409);
        $this->assertSame('pending', $topup->fresh()->Status);
        $this->assertDatabaseCount('Transaction_tb', 1);
        $this->assertSame(300, (int) $this->user->wallet()->first()->Wallet_count);
        $this->postJson("/api/topups/{$topup->Topup_id}/sync")->assertOk();
        $this->postJson("/api/topups/{$topup->Topup_id}/sync")->assertOk();
        $this->assertSame(320, (int) $this->user->wallet()->first()->Wallet_count);
    }

    public function test_modifying_history_and_balance_repairs_history_then_wallet_on_next_attempt(): void
    {
        $this->opening->update(['T_cost' => 900]);
        $this->user->wallet()->update(['Wallet_count' => 900]);
        $this->postJson('/api/topups', ['amount' => 20])->assertStatus(409)->assertJsonPath('code', 'transaction_data_restored');
        $this->assertSame(300, (int) $this->opening->fresh()->T_cost);
        Http::assertNothingSent();
        $this->assertSame(900, (int) $this->user->wallet()->first()->Wallet_count);
        $this->postJson('/api/topups', ['amount' => 20])->assertStatus(409)->assertJsonPath('code', 'wallet_balance_corrected');
        $this->assertSame(300, (int) $this->user->wallet()->first()->Wallet_count);
    }

    public function test_deleted_history_is_blocked(): void
    {
        $this->opening->delete();
        $this->postJson('/api/topups', ['amount' => 20])->assertStatus(409);
    }

    public function test_pending_ledger_is_not_accepted_as_trusted_history(): void
    {
        Transaction_tb::create(['User_id' => $this->user->User_id, 'T_type' => 'topup_credit', 'T_cost' => 20]);
        $this->user->wallet()->update(['Wallet_count' => 320]);
        $this->postJson('/api/topups', ['amount' => 20])->assertStatus(409)
            ->assertJsonPath('message', 'หลักฐานธุรกรรมยังไม่ครบ กรุณารอการตรวจสอบแล้วลองใหม่');
        $this->assertSame(320, (int) $this->user->wallet()->first()->Wallet_count);
    }

    public function test_no_valid_majority_blocks_spending(): void
    {
        foreach ([1, 2] as $node) {
            Storage::put("blockchain/node_{$node}_ledger.json", 'invalid');
        }
        $this->postJson('/api/topups', ['amount' => 20])->assertStatus(409);
    }
}
