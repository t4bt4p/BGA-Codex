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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SrsHistoryAuthorizationTest extends TestCase
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
        $wallet = Wallet_tb::create(['Wallet_count' => $balance]);

        return User::create(['User_username' => $name, 'User_name' => $name,
            'User_password' => Hash::make('SrsTest12!'), 'User_phone' => '0812345678',
            'User_status' => 1, 'User_role' => $role, 'Wallet_id' => $wallet->Wallet_id]);
    }

    private function api(string $method, string $path, ?string $token = null, array $data = [])
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->json($method, $path, $data, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['User_tb', 'Wallet_tb', 'Boardgame_tb', 'Rental_tb', 'Transaction_tb', 'Topup_request_tb'] as $table) {
            $result[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $result;
    }

    private function rental(User $owner): Rental_tb
    {
        $category = Boardgame_category_tb::firstOrCreate(['Bg_category_name' => 'SRS33']);
        $game = Boardgame_tb::create(['Bg_name' => 'Owner game '.$owner->User_id, 'Bg_cost' => 30,
            'Bg_min_player' => 2, 'Bg_max_player' => 4, 'Bg_playduration' => 60,
            'Bg_Catetogory_id' => $category->Bg_category_id, 'Bg_use_status' => 0, 'Bg_Image' => '']);

        return Rental_tb::create(['User_id' => $owner->User_id, 'Bg_id' => $game->Bg_id,
            'Rental_cost' => 30, 'rental_days' => 1, 'daily_rate' => 30,
            'rented_at' => now(), 'due_at' => now()->addDay(), 'Rental_status' => 'active']);
    }

    public function test_tc32_history_api_returns_exact_own_fields_and_read_is_side_effect_free(): void
    {
        $a = $this->member('history_a');
        $b = $this->member('history_b', 'user', 999);
        $transactions = [];
        foreach (['topup_credit', 'rental_debit', 'return_event', 'admin_debit'] as $i => $type) {
            $transactions[] = Transaction_tb::create(['User_id' => $a->User_id, 'T_type' => $type,
                'T_cost' => $type === 'return_event' ? 0 : 20 + $i, 'Chain_status' => $i ? 'pending' : 'confirmed']);
        }
        $foreign = Transaction_tb::create(['User_id' => $b->User_id, 'T_type' => 'topup_credit', 'T_cost' => 987]);
        $token = $a->createToken('history')->plainTextToken;
        $before = $this->snapshot();
        Queue::fake();
        foreach (['/api/transactions', '/api/transactions?User_id='.$b->User_id.'&user_id='.$b->User_id] as $path) {
            $rows = $this->api('GET', $path, $token)->assertOk()->assertJsonCount(4)->json();
            $this->assertNotContains($foreign->Ts_id, array_column($rows, 'Ts_id'));
            foreach ($transactions as $tx) {
                $row = collect($rows)->firstWhere('Ts_id', $tx->Ts_id);
                foreach (['User_id', 'T_type', 'T_cost', 'Chain_status'] as $key) {
                    $this->assertEquals($tx->$key, $row[$key]);
                }
                $this->assertSame($tx->fresh()->toArray()['created_at'], $row['created_at']);
                $this->assertSame($a->User_id, $row['user']['User_id']);
            }
        }
        $this->assertSame($before, $this->snapshot());
        Queue::assertNothingPushed();
    }

    public function test_tc32_history_empty_array_does_not_change_wallet_or_transactions(): void
    {
        $a = $this->member('history_empty');
        $token = $a->createToken('empty')->plainTextToken;
        $before = $this->snapshot();
        $this->api('GET', '/api/transactions', $token)->assertOk()->assertExactJson([]);
        $this->assertSame($before, $this->snapshot());
        Queue::assertNothingPushed();
    }

    public function test_tc33_missing_invalid_revoked_and_suspended_tokens_are_denied_without_mutations(): void
    {
        $a = $this->member('tokens');
        $valid = $a->createToken('valid')->plainTextToken;
        $revoked = $a->createToken('revoked');
        $revokedText = $revoked->plainTextToken;
        $revoked->accessToken->delete();
        $suspended = $this->member('suspended');
        $suspended->update(['User_status' => 0]);
        $suspendedToken = $suspended->createToken('suspended')->plainTextToken;
        $before = $this->snapshot();
        foreach ([[null, 401], ['invalid-token', 401], [$revokedText, 401], [$suspendedToken, 403]] as [$token, $status]) {
            foreach (['/api/user', '/api/transactions', '/api/rentals/active'] as $path) {
                $this->api('GET', $path, $token)->assertStatus($status);
                $this->assertSame($before, $this->snapshot());
            }
        }
        $this->api('GET', '/api/user', $valid)->assertOk()->assertJsonPath('User_id', $a->User_id);
        Queue::assertNothingPushed();
    }

    public function test_tc33_member_cannot_use_admin_apis_even_with_forged_role(): void
    {
        $a = $this->member('role_a');
        $b = $this->member('role_b');
        $tx = Transaction_tb::create(['User_id' => $b->User_id, 'T_type' => 'topup_credit', 'T_cost' => 20]);
        $token = $a->createToken('role')->plainTextToken;
        $before = $this->snapshot();
        Queue::fake();
        foreach ([['GET', '/api/users'], ['GET', '/api/rentals'], ['GET', '/api/topups'], ['GET', '/api/reports/dashboard'],
            ['GET', '/api/blockchain/verify'], ['POST', '/api/blockchain/repair'], ['POST', '/api/blockchain/reconcile'],
            ['PUT', '/api/users/'.$b->User_id.'/wallet'], ['PUT', '/api/users/'.$b->User_id.'/status'],
            ['POST', '/api/categories'], ['POST', '/api/boardgames'], ['POST', '/api/transactions/'.$tx->Ts_id.'/polygon/retry']] as [$method,$path]) {
            $this->api($method, $path, $token, ['role' => 'admin', 'User_role' => 'admin', 'amount' => 20, 'action' => 'add', 'User_status' => 0])->assertForbidden();
            $this->assertSame($before, $this->snapshot());
        }
        Queue::assertNothingPushed();
    }

    public function test_tc33_cross_member_mutations_denied_and_reads_remain_scoped_to_owner(): void
    {
        $a = $this->member('owner_a');
        $b = $this->member('owner_b', 'user', 999);
        $r = $this->rental($b);
        $topup = Topup_request_tb::create(['User_id' => $b->User_id, 'Amount' => 20, 'Reference' => 'SRS33-OTHER', 'Status' => 'pending']);
        Transaction_tb::create(['User_id' => $b->User_id, 'T_type' => 'rental_debit', 'T_cost' => 30, 'Rental_id' => $r->Rental_id]);
        $token = $a->createToken('owner')->plainTextToken;
        $before = $this->snapshot();
        Queue::fake();
        $this->api('POST', '/api/rentals/return', $token, ['rental_id' => $r->Rental_id, 'User_id' => $b->User_id])->assertForbidden();
        $this->api('POST', '/api/topups/'.$topup->Topup_id.'/sync', $token)->assertForbidden();
        $this->api('PUT', '/api/users/'.$b->User_id.'/wallet', $token, ['amount' => 20, 'action' => 'deduct'])->assertForbidden();
        $this->api('GET', '/api/user?User_id='.$b->User_id, $token)->assertOk()->assertJsonPath('User_id', $a->User_id)->assertJsonPath('wallet.Wallet_count', 100);
        $this->api('GET', '/api/transactions?User_id='.$b->User_id, $token)->assertOk()->assertExactJson([]);
        $this->api('GET', '/api/rentals/active?User_id='.$b->User_id, $token)->assertOk()->assertExactJson([]);
        $this->assertSame($before, $this->snapshot());
        Queue::assertNothingPushed();
    }

    public function test_tc33_owner_and_authorized_admin_positive_paths_including_admin_return(): void
    {
        $owner = $this->member('positive_owner');
        $admin = $this->member('positive_admin', 'admin');
        $r = $this->rental($owner);
        $token = $owner->createToken('own')->plainTextToken;
        $this->api('GET', '/api/user', $token)->assertOk()->assertJsonPath('User_id', $owner->User_id);
        $this->api('GET', '/api/rentals/active', $token)->assertOk()->assertJsonCount(1);
        $this->api('PUT', '/api/user/profile', $token, ['User_name' => 'Updated own name', 'User_phone' => '0899999999'])->assertOk();
        $this->api('POST', '/api/rentals/return', $token, ['rental_id' => $r->Rental_id])->assertOk();
        $this->assertSame($owner->User_id, (int) $r->fresh()->returned_by_user_id);
        $r2 = $this->rental($owner);
        $owner->update(['User_status' => 0]);
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $this->api('GET', '/api/users', $adminToken)->assertOk();
        $this->api('GET', '/api/transactions', $adminToken)->assertOk();
        $this->api('POST', '/api/rentals/return', $adminToken, ['rental_id' => $r2->Rental_id])->assertOk();
        $this->assertSame($admin->User_id, (int) $r2->fresh()->returned_by_user_id);
        $this->assertSame(100, (int) $owner->wallet->fresh()->Wallet_count);
        $this->assertSame(2,Transaction_tb::where('User_id',$owner->User_id)->where('T_type','return_event')->count());
    }
}
