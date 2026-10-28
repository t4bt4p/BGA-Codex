<?php

namespace Tests\Feature;

use App\Models\Boardgame_category_tb;
use App\Models\Boardgame_tb;
use App\Models\Rental_tb;
use App\Models\Topup_request_tb;
use App\Models\Transaction_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LatestSrsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
        config(['services.polygon.enabled' => false, 'services.opn.secret_key' => 'skey_test_example', 'services.opn.live_mode' => false]);
    }

    private function user($balance = 0, $role = 'user'): User
    {
        $w = Wallet_tb::create(['Wallet_count' => $balance]);

        return User::create(['User_username' => 'latest'.$w->Wallet_id, 'User_name' => 'Latest SRS', 'User_password' => Hash::make('secret12'), 'User_phone' => '0800000001', 'User_status' => 1, 'User_role' => $role, 'Wallet_id' => $w->Wallet_id]);
    }

    private function game(): Boardgame_tb
    {
        $c = Boardgame_category_tb::create(['Bg_category_name' => 'Strategy']);

        return Boardgame_tb::create(['Bg_name' => 'Latest Game', 'Bg_cost' => 30, 'Bg_min_player' => 2, 'Bg_max_player' => 4, 'Bg_playduration' => 60, 'Bg_Catetogory_id' => $c->Bg_category_id, 'Bg_use_status' => 1, 'Bg_Image' => '']);
    }

    public function test_dashboard_all_period_includes_rentals_from_previous_months(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $user = $this->user();
        $game = $this->game();
        Rental_tb::create(['User_id' => $user->User_id, 'Bg_id' => $game->Bg_id, 'Rental_cost' => 60, 'rented_at' => '2026-09-15 10:00:00', 'due_at' => '2026-09-17 10:00:00', 'returned_at' => '2026-09-16 10:00:00', 'Rental_status' => 'returned']);
        Sanctum::actingAs($this->user(0, 'admin'));
        $this->getJson('/api/reports/dashboard?month=2026-10')->assertOk()->assertJsonPath('period_rentals', 0);
        $report = $this->getJson('/api/reports/dashboard?all=1')->assertOk()
            ->assertJsonPath('period.label', 'ทั้งหมด')->assertJsonPath('period_rentals', 1)
            ->assertJsonPath('period_revenue', 60)->assertJsonPath('popular_games.0.rental_count', 1);
        $this->assertSame(1, array_sum(array_column($report->json('daily_rentals'), 'count')));
    }

    public function test_t_c02_missing_phone_must_not_register(): void
    {
        $this->postJson('/api/register', ['User_name' => 'No phone', 'username' => 'nophone', 'password' => 'secret12'])->assertUnprocessable();
        $this->assertDatabaseCount('User_tb', 0);
        $this->assertDatabaseCount('Wallet_tb', 0);
    }

    public function test_t_c17_cannot_delete_game_with_active_rental(): void
    {
        $u = $this->user(100);
        $g = $this->game();
        Sanctum::actingAs($u);
        $this->postJson('/api/rentals/rent', ['Bg_id' => $g->Bg_id])->assertCreated();
        Sanctum::actingAs($this->user(0, 'admin'));
        $this->deleteJson('/api/boardgames/'.$g->Bg_id)->assertStatus(409);
        $this->assertNotSoftDeleted('Boardgame_tb', ['Bg_id' => $g->Bg_id]);
    }

    public function test_t_c19_full_year_report_supported_without_day_limit(): void
    {
        Sanctum::actingAs($this->user(0, 'admin'));
        $this->getJson('/api/reports/dashboard?from=2026-01-01&to=2026-12-31')->assertOk()->assertJsonCount(365, 'daily_rentals');
    }

    public function test_t_c27_invalid_amounts_do_not_create_topup(): void
    {
        Sanctum::actingAs($this->user());
        foreach ([0, 21, 1.5] as $amount) {
            $this->postJson('/api/topups', ['amount' => $amount])->assertUnprocessable();
        }$this->assertDatabaseCount('Topup_request_tb', 0);
        $this->assertDatabaseCount('Transaction_tb', 0);
    }

    public function test_t_c27_failed_charge_returns_explanation_without_credit(): void
    {
        $u = $this->user();
        $t = Topup_request_tb::create(['User_id' => $u->User_id, 'Amount' => 20, 'Reference' => 'REF20', 'Provider_charge_id' => 'charge20', 'Status' => 'pending']);
        Sanctum::actingAs($u);
        Http::fake(['api.omise.co/charges/*' => Http::response(['id' => 'charge20', 'status' => 'failed', 'paid' => false, 'livemode' => false, 'amount' => 2000, 'currency' => 'THB', 'metadata' => ['reference' => 'REF20']])]);
        $r = $this->postJson('/api/topups/'.$t->Topup_id.'/sync');
        $this->assertSame(0, (int) $u->wallet->fresh()->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 0);
        $this->assertNotEmpty($r->json('message') ?? $r->json('reason'), 'Failed Charge returns pending without explaining payment failure.');
    }

    public function test_t_c31_own_balance_after_topup_rental_and_blocked_admin_adjustment(): void
    {
        $u = $this->user(0);
        $other = $this->user(999);
        Sanctum::actingAs($u);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('User_id', $u->User_id)->assertJsonPath('wallet.Wallet_count', 0);
        $u->wallet->update(['Wallet_count' => 100]);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('wallet.Wallet_count', 100);
        $t = Topup_request_tb::create(['User_id' => $u->User_id, 'Amount' => 50, 'Reference' => 'REF50', 'Provider_charge_id' => 'charge50', 'Status' => 'pending']);
        Http::fake(['api.omise.co/charges/*' => Http::response(['id' => 'charge50', 'status' => 'successful', 'paid' => true, 'livemode' => false, 'amount' => 5000, 'currency' => 'THB', 'metadata' => ['reference' => 'REF50']])]);
        $this->postJson('/api/topups/'.$t->Topup_id.'/sync')->assertOk();
        $this->getJson('/api/user')->assertJsonPath('wallet.Wallet_count', 150);
        $g = $this->game();
        $this->postJson('/api/rentals/rent', ['Bg_id' => $g->Bg_id, 'rental_days' => 2])->assertCreated();
        $this->getJson('/api/user')->assertJsonPath('wallet.Wallet_count', 90);
        Sanctum::actingAs($this->user(0, 'admin'));
        $this->putJson('/api/users/'.$u->User_id.'/wallet', ['amount' => 20, 'action' => 'add'])->assertForbidden();
        $this->assertSame(90, (int) $u->wallet->fresh()->Wallet_count);
        $this->assertDatabaseMissing('Transaction_tb', ['User_id' => $u->User_id, 'T_type' => 'topup_credit', 'T_cost' => 20]);
        $this->putJson('/api/users/'.$u->User_id.'/wallet', ['amount' => 20, 'action' => 'deduct'])->assertForbidden();
        $this->assertDatabaseMissing('Transaction_tb', ['User_id' => $u->User_id, 'T_type' => 'admin_debit']);
        Sanctum::actingAs($u);
        $this->getJson('/api/user')->assertJsonPath('wallet.Wallet_count', 90)->assertJsonPath('User_id', $u->User_id);
        $this->assertSame(999, (int) $other->wallet->fresh()->Wallet_count);
    }

    public function test_t_c19_reversed_or_incomplete_date_range_is_rejected(): void
    {
        Sanctum::actingAs($this->user(0, 'admin'));
        foreach (['from=2026-12-31&to=2026-01-01', 'from=2026-01-01', 'to=2026-12-31'] as $query) {
            $this->getJson('/api/reports/dashboard?'.$query)->assertUnprocessable();
        }
    }

    public function test_t_c17_soft_delete_after_return_preserves_rental_and_transaction_history(): void
    {
        $u = $this->user(100);
        $g = $this->game();
        Sanctum::actingAs($u);
        $id = $this->postJson('/api/rentals/rent', ['Bg_id' => $g->Bg_id])->assertCreated()->json('rental.Rental_id');
        $this->postJson('/api/rentals/return', ['rental_id' => $id])->assertOk();
        Sanctum::actingAs($this->user(0, 'admin'));
        $this->deleteJson('/api/boardgames/'.$g->Bg_id)->assertOk();
        $this->assertSoftDeleted('Boardgame_tb', ['Bg_id' => $g->Bg_id]);
        $this->assertSame($g->Bg_name, Rental_tb::findOrFail($id)->boardgame->Bg_name);
        $this->assertSame($g->Bg_name, Transaction_tb::where('Rental_id', $id)->firstOrFail()->boardgame->Bg_name);
        $this->getJson('/api/boardgames')->assertJsonMissing(['Bg_id' => $g->Bg_id]);
    }

    public function test_t_c27_expired_payment_has_failure_status_and_reason_without_ledger_job(): void
    {
        $u = $this->user();
        $t = Topup_request_tb::create(['User_id' => $u->User_id, 'Amount' => 20, 'Reference' => 'EXPIRED', 'Provider_charge_id' => 'expired20', 'Status' => 'pending']);
        Sanctum::actingAs($u);
        Http::fake(['api.omise.co/charges/*' => Http::response(['id' => 'expired20', 'status' => 'expired', 'paid' => false, 'livemode' => false])]);
        $this->postJson('/api/topups/'.$t->Topup_id.'/sync')->assertOk()->assertJsonPath('status', 'failed');
        $this->assertSame('failed', $t->fresh()->Status);
        $this->assertSame(0, (int) $u->wallet->fresh()->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 0);
        Queue::assertNothingPushed();
    }

    public function test_explicit_invalid_rental_days_never_create_a_zero_day_rental(): void
    {
        $user = $this->user(100);
        $game = $this->game();
        Sanctum::actingAs($user);
        foreach ([null, 0, 8, 1.5, 'not-days'] as $days) {
            $this->postJson('/api/rentals/rent', ['Bg_id' => $game->Bg_id, 'rental_days' => $days])
                ->assertUnprocessable()->assertJsonValidationErrors('rental_days');
        }
        $this->assertSame(100, (int) $user->wallet->fresh()->Wallet_count);
        $this->assertSame(1, (int) $game->fresh()->Bg_use_status);
        $this->assertDatabaseCount('Rental_tb', 0);
        $this->assertDatabaseCount('Transaction_tb', 0);
        Queue::assertNothingPushed();
    }
}
