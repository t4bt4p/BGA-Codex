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
use Tests\TestCase;

class SrsAcceptanceRerunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
        config(['services.polygon.enabled' => false]);
    }

    private function member(string $name, string $role = 'user', int $balance = 100): User
    {
        $w = Wallet_tb::create(['Wallet_count' => $balance]);

        return User::create(['User_username' => $name, 'User_name' => $name, 'User_password' => Hash::make('Rerun12!'),
            'User_phone' => '0812345678', 'User_role' => $role, 'User_status' => 1, 'Wallet_id' => $w->Wallet_id]);
    }

    private function api(string $method, string $path, ?string $token = null, array $data = [])
    {
        // Model separate stateless HTTP requests: login's in-memory web session
        // must not bypass Sanctum's bearer-token authentication on the next call.
        $this->app['session']->driver()->flush();
        $this->app['auth']->shouldUse('web');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->json($method, $path, $data, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function game(): Boardgame_tb
    {
        $c = Boardgame_category_tb::firstOrCreate(['Bg_category_name' => 'Rerun category']);

        return Boardgame_tb::create(['Bg_name' => 'Rerun game', 'Bg_cost' => 30, 'Bg_min_player' => 2, 'Bg_max_player' => 4,
            'Bg_playduration' => 60, 'Bg_Catetogory_id' => $c->Bg_category_id, 'Bg_use_status' => 1, 'Bg_Image' => '']);
    }

    public function test_tc02_duplicate_short_password_and_missing_fields_leave_database_unchanged(): void
    {
        $this->member('duplicate');
        $base = ['username' => 'fresh', 'User_name' => 'Fresh', 'User_phone' => '0812345678', 'password' => 'Rerun12!'];
        foreach ([['username' => 'duplicate'], ['password' => '12345'], ['User_name' => ''], ['User_phone' => ''], ['username' => '']] as $invalid) {
            $this->api('POST', '/api/register', null, array_replace($base, $invalid))->assertUnprocessable();
            $this->assertDatabaseCount('User_tb', 1);
            $this->assertDatabaseCount('Wallet_tb', 1);
        }
    }

    public function test_tc03_tc04_real_member_and_admin_login_tokens_are_revoked_after_logout(): void
    {
        foreach (['user', 'admin'] as $role) {
            $u = $this->member('login_'.$role, $role);
            $r = $this->api('POST', $role === 'admin' ? '/api/admin/login' : '/api/login', null, ['username' => $u->User_username, 'password' => 'Rerun12!'])->assertOk();
            $token = $r->json('token');
            $this->assertNotEmpty($token);
            $this->assertSame($role, $r->json('user.User_role'));
            $this->api('GET', '/api/user', $token)->assertOk();
            $this->api('POST', '/api/logout', $token)->assertOk();
            $this->api('GET', '/api/user', $token)->assertUnauthorized();
        }
    }

    public function test_tc05_guest_can_read_public_games_but_cannot_transact(): void
    {
        $g = $this->game();
        $u = $this->member('private_member');
        $r = $this->api('GET', '/api/boardgames')->assertOk()->assertJsonPath('0.Bg_id', $g->Bg_id);
        $this->assertStringNotContainsString('User_password', $r->getContent());
        $this->assertStringNotContainsString($u->User_username, $r->getContent());
        $this->api('GET', '/api/categories')->assertOk();
        foreach (['/api/rentals/rent', '/api/rentals/return', '/api/topups'] as $path) {
            $this->api('POST', $path, null, ['Bg_id' => $g->Bg_id, 'amount' => 20])->assertUnauthorized();
        }
        $this->assertDatabaseCount('Transaction_tb', 0);
        $this->assertDatabaseCount('Rental_tb', 0);
    }

    public function test_tc10_all_seven_rental_day_values_apply_correct_rate_and_state(): void
    {
        foreach (range(1, 7) as $days) {
            $u = $this->member('days_'.$days, 'user', 1000);
            $g = $this->game();
            $token = $u->createToken('days')->plainTextToken;
            $r = $this->api('POST', '/api/rentals/rent', $token, ['Bg_id' => $g->Bg_id, 'rental_days' => $days])->assertCreated();
            $id = $r->json('rental.Rental_id');
            $rental = Rental_tb::findOrFail($id);
            $this->assertSame(30 * $days, (int) $rental->Rental_cost);
            $this->assertSame(1000 - 30 * $days, (int) $u->wallet->fresh()->Wallet_count);
            $this->assertSame(0, (int) $g->fresh()->Bg_use_status);
            $this->assertDatabaseHas('Transaction_tb', ['Rental_id' => $id, 'T_type' => 'rental_debit', 'T_cost' => 30 * $days]);
        }
    }

    public function test_tc11_unavailable_insufficient_and_suspended_reject_without_business_changes(): void
    {
        foreach (['unavailable', 'insufficient', 'suspended'] as $mode) {
            $u = $this->member($mode, 'user', $mode === 'insufficient' ? 0 : 100);
            $g = $this->game();
            if ($mode === 'unavailable') {
                $g->update(['Bg_use_status' => 0]);
            }
            if ($mode === 'suspended') {
                $u->update(['User_status' => 0]);
            }
            $token = $u->createToken('invalid')->plainTextToken;
            $balance = $u->wallet->Wallet_count;
            $status = $g->Bg_use_status;
            $r = $this->api('POST', '/api/rentals/rent', $token, ['Bg_id' => $g->Bg_id, 'rental_days' => 1]);
            $this->assertContains($r->status(), [403, 409, 422]);
            $this->assertNotEmpty($r->json('message'));
            $this->assertSame((int) $balance, (int) $u->wallet->fresh()->Wallet_count);
            $this->assertSame((int) $status, (int) $g->fresh()->Bg_use_status);
            $this->assertDatabaseCount('Transaction_tb', 0);
            $this->assertDatabaseCount('Rental_tb', 0);
        }
    }

    public function test_tc18_tc19_report_same_day_month_and_full_year_use_recorded_values(): void
    {
        $admin = $this->member('report_admin', 'admin');
        $u = $this->member('report_owner');
        $g = $this->game();
        foreach (['2026-01-02 12:00:00', '2026-01-03 12:00:00', '2026-02-01 12:00:00'] as $date) {
            Rental_tb::create(['User_id' => $u->User_id, 'Bg_id' => $g->Bg_id, 'Rental_cost' => 30, 'rental_days' => 1, 'daily_rate' => 30, 'rented_at' => $date, 'due_at' => '2026-12-31', 'Rental_status' => 'returned', 'returned_at' => '2026-02-02']);
        }
        $token = $admin->createToken('reports')->plainTextToken;
        foreach ([['from=2026-01-02&to=2026-01-02', 1], ['month=2026-01', 2], ['from=2026-01-01&to=2026-12-31', 3]] as [$query,$count]) {
            $this->api('GET', '/api/reports/dashboard?'.$query, $token)->assertOk()->assertJsonPath('period_rentals', $count)
                ->assertJsonPath('period_revenue', 30 * $count)->assertJsonPath('active_rentals', 0)->assertJsonPath('overdue_rentals', 0)->assertJsonPath('popular_games.0.rental_count', $count);
        }
    }

    public function test_tc20_tc21_admin_topup_and_transaction_fields_match_database(): void
    {
        $admin = $this->member('listing_admin', 'admin');
        $u = $this->member('listing_user');
        $topup = Topup_request_tb::create(['User_id' => $u->User_id, 'Amount' => 50, 'Reference' => 'LISTING-REF', 'Provider_charge_id' => 'LISTING-CHARGE', 'Status' => 'approved', 'approved_at' => now()]);
        $tx = Transaction_tb::create(['User_id' => $u->User_id, 'T_type' => 'topup_credit', 'T_cost' => 50]);
        $token = $admin->createToken('list')->plainTextToken;
        $r = $this->api('GET', '/api/topups', $token)->assertOk();
        $rows = $r->json();
        $row = collect($rows)->firstWhere('Topup_id', $topup->Topup_id);
        foreach (['Amount', 'Reference', 'Status', 'User_id'] as $key) {
            $this->assertEquals($topup->$key, $row[$key]);
        }
        $this->assertNotEmpty($row['created_at']);
        $this->assertNotEmpty($row['approved_at']);
        $this->api('GET', '/api/transactions', $token)->assertOk()->assertJsonPath('0.Ts_id', $tx->Ts_id)->assertJsonPath('0.T_cost', 50);
    }

    public function test_tc30_invalid_adjustments_never_change_wallet_or_create_transactions(): void
    {
        $admin = $this->member('adjust_admin', 'admin');
        $u = $this->member('adjust_user');
        $token = $admin->createToken('adjust')->plainTextToken;
        foreach ([['amount' => 0, 'action' => 'add'], ['amount' => -1, 'action' => 'add'], ['amount' => 1.5, 'action' => 'add'], ['amount' => 101, 'action' => 'deduct']] as $data) {
            $this->api('PUT','/api/users/'.$u->User_id.'/wallet',$token,$data)->assertForbidden()->assertJsonStructure(['message']);
            $this->assertSame(100,(int) $u->wallet->fresh()->Wallet_count);
            $this->assertDatabaseCount('Transaction_tb',0);
        }
        Queue::assertNothingPushed();
    }
}
