<?php

namespace Tests\Feature;

use App\Models\Boardgame_category_tb;
use App\Models\Boardgame_tb;
use App\Models\Rental_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use App\Services\BlockchainService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoreFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_registration_accepts_an_omitted_phone_and_creates_wallet(): void
    {
        $this->postJson('/api/register', [
            'User_name' => 'ผู้ใช้ทดสอบ',
            'username' => 'customer1',
            'password' => 'secret12',
        ])->assertCreated()->assertJsonPath('user.User_phone', null);

        $this->assertDatabaseHas('Wallet_tb', ['Wallet_count' => 0]);
        $this->assertDatabaseHas('User_tb', ['User_username' => 'customer1', 'User_phone' => null]);
    }

    public function test_user_can_only_edit_profile_fields_defined_by_the_document(): void
    {
        $user = $this->makeUser(0);
        Sanctum::actingAs($user);

        $this->putJson('/api/user/profile', [
            'User_name' => 'ชื่อใหม่',
            'User_phone' => '081-234-5678',
            'User_role' => 'admin',
        ])->assertOk();

        $this->assertDatabaseHas('User_tb', ['User_id' => $user->User_id, 'User_name' => 'ชื่อใหม่', 'User_role' => 'user']);
    }

    public function test_renting_a_game_atomically_debits_wallet_and_marks_game_unavailable(): void
    {
        $user = $this->makeUser(100);
        $category = Boardgame_category_tb::create(['Bg_category_name' => 'วางแผน']);
        $game = Boardgame_tb::create([
            'Bg_name' => 'Test Game', 'Bg_cost' => 30, 'Bg_min_player' => 2,
            'Bg_max_player' => 4, 'Bg_playduration' => 60,
            'Bg_Catetogory_id' => $category->Bg_category_id, 'Bg_use_status' => 1, 'Bg_Image' => '',
        ]);
        app(BlockchainService::class)->addTransaction(['type' => 'topup_credit', 'user_id' => $user->User_id, 'cost' => 100]);
        Sanctum::actingAs($user);

        $this->postJson('/api/rentals/rent', ['Bg_id' => $game->Bg_id, 'rental_days' => 2])->assertCreated()->assertJsonPath('balance', 40);

        $this->assertDatabaseHas('Wallet_tb', ['Wallet_id' => $user->Wallet_id, 'Wallet_count' => 40]);
        $this->assertDatabaseHas('Boardgame_tb', ['Bg_id' => $game->Bg_id, 'Bg_use_status' => 0]);
        $this->assertDatabaseHas('Rental_tb', ['User_id' => $user->User_id, 'rental_days' => 2, 'daily_rate' => 30, 'Rental_cost' => 60]);
        $this->assertDatabaseHas('Transaction_tb', ['User_id' => $user->User_id, 'T_type' => 'rental_debit', 'T_cost' => 60]);
    }

    public function test_returning_a_game_always_creates_a_neutral_blockchain_transaction(): void
    {
        $user = $this->makeUser(100);
        $game = $this->makeGame();
        app(BlockchainService::class)->addTransaction(['type' => 'topup_credit', 'user_id' => $user->User_id, 'cost' => 100]);
        Sanctum::actingAs($user);
        $rentalId = $this->postJson('/api/rentals/rent', ['Bg_id' => $game->Bg_id])->json('rental.Rental_id');

        $this->postJson('/api/rentals/return', ['rental_id' => $rentalId])->assertOk();

        $this->assertDatabaseHas('Transaction_tb', ['User_id' => $user->User_id, 'Bg_id' => $game->Bg_id, 'T_type' => 'return_event', 'T_cost' => 0]);
        $this->assertSame(70, app(BlockchainService::class)->getRealBalance($user->User_id));
        $this->assertDatabaseHas('Boardgame_tb', ['Bg_id' => $game->Bg_id, 'Bg_use_status' => 1]);
    }

    public function test_admin_report_is_limited_to_the_selected_month(): void
    {
        $admin = $this->makeUser(0, 'admin');
        $customer = $this->makeUser(0);
        $game = $this->makeGame();
        foreach ([['2026-08-05 10:00:00', 30], ['2026-08-20 10:00:00', 30], ['2026-07-20 10:00:00', 99]] as [$date, $cost]) {
            Rental_tb::create([
                'User_id' => $customer->User_id, 'Bg_id' => $game->Bg_id, 'Rental_cost' => $cost,
                'rented_at' => Carbon::parse($date), 'due_at' => Carbon::parse($date)->addDays(7), 'Rental_status' => 'returned',
            ]);
        }
        Sanctum::actingAs($admin);

        $this->getJson('/api/reports/dashboard?month=2026-08')
            ->assertOk()
            ->assertJsonPath('period_rentals', 2)
            ->assertJsonPath('period_revenue', 60)
            ->assertJsonPath('period.from', '2026-08-01')
            ->assertJsonCount(31, 'daily_rentals');
    }

    public function test_overdue_account_is_blocked_with_an_existing_token_and_at_login(): void
    {
        $user = $this->makeUser(100);
        $this->makeOverdueRental($user);
        $token = $user->createToken('existing')->plainTextToken;
        $this->withToken($token)->getJson('/api/user')->assertForbidden()
            ->assertJsonPath('code', 'account_suspended')
            ->assertJsonPath('reason', 'overdue')
            ->assertJsonPath('message', 'บัญชีของคุณถูกระงับเนื่องจากบอร์ดเกมเกินกำหนด');
        $this->withToken($token)->getJson('/api/transactions')->assertForbidden();
        $this->withToken($token)->postJson('/api/topups', ['amount' => 100])->assertForbidden();
        $this->assertSame(0, (int) $user->fresh()->User_status);
        app('auth')->shouldUse('web');
        $this->postJson('/api/login', ['username' => $user->User_username, 'password' => 'secret12'])
            ->assertForbidden()->assertJsonPath('code', 'account_suspended')
            ->assertJsonPath('message', 'บัญชีของคุณถูกระงับเนื่องจากบอร์ดเกมเกินกำหนด');
    }

    public function test_manually_disabled_account_does_not_claim_an_overdue_rental(): void
    {
        $user = $this->makeUser(0);
        $user->update(['User_status' => 0]);
        $this->postJson('/api/login', ['username' => $user->User_username, 'password' => 'secret12'])
            ->assertForbidden()->assertJsonPath('code', 'account_suspended')->assertJsonPath('reason', 'disabled');
    }

    public function test_scheduler_suspends_only_active_rentals_past_the_deadline(): void
    {
        $this->freezeTime();
        $late = $this->makeUser(100);
        $dueNow = $this->makeUser(100);
        $returned = $this->makeUser(100);
        $this->makeOverdueRental($late);
        $this->makeOverdueRental($dueNow)->update(['due_at' => now()]);
        $this->makeOverdueRental($returned)->update(['Rental_status' => 'returned', 'returned_at' => now()]);
        $this->artisan('rentals:suspend-overdue')->assertSuccessful();
        $this->assertSame(0, (int) $late->fresh()->User_status);
        $this->assertSame(1, (int) $dueNow->fresh()->User_status);
        $this->assertSame(1, (int) $returned->fresh()->User_status);
    }

    public function test_admin_cannot_rent_or_reactivate_overdue_account_but_can_return_and_reactivate(): void
    {
        $user = $this->makeUser(100);
        $rental = $this->makeOverdueRental($user);
        Sanctum::actingAs($this->makeUser(0, 'admin'));
        $this->postJson('/api/rentals/rent', ['User_id' => $user->User_id, 'Bg_id' => $this->makeGame()->Bg_id])
            ->assertForbidden();
        $this->putJson('/api/users/'.$user->User_id.'/status', ['User_status' => 1])->assertUnprocessable();
        $this->postJson('/api/rentals/return', ['rental_id' => $rental->Rental_id])->assertOk();
        $this->assertDatabaseHas('Rental_tb', [
            'Rental_id' => $rental->Rental_id,
            'User_id' => $user->User_id,
            'returned_by_name' => 'Admin',
            'returned_by_user_id' => auth()->id(),
        ]);
        $this->assertSame(0, (int) $user->fresh()->User_status);
        $this->putJson('/api/users/'.$user->User_id.'/status', ['User_status' => 1])->assertOk();
    }

    public function test_rented_game_cannot_be_edited_until_returned(): void
    {
        $user = $this->makeUser(100);
        $rental = $this->makeOverdueRental($user);
        $game = $rental->boardgame;
        Sanctum::actingAs($this->makeUser(0, 'admin'));
        $data = $game->only(['Bg_name', 'Bg_cost', 'Bg_min_player', 'Bg_max_player', 'Bg_playduration', 'Bg_Catetogory_id']);
        $data['Bg_name'] = 'Updated game';
        $this->putJson('/api/boardgames/'.$game->Bg_id, $data)->assertStatus(409);
        $this->assertSame('Test Game', $game->fresh()->Bg_name);
        $this->postJson('/api/rentals/return', ['rental_id' => $rental->Rental_id])->assertOk();
        $this->putJson('/api/boardgames/'.$game->Bg_id, $data)->assertOk();
        $this->assertSame('Updated game', $game->fresh()->Bg_name);
    }

    public function test_admin_can_return_overdue_game_without_charging_a_fee(): void
    {
        $user = $this->makeUser(0);
        $rental = $this->makeOverdueRental($user);
        $game = $rental->boardgame;
        Sanctum::actingAs($this->makeUser(0, 'admin'));

        $this->postJson('/api/rentals/return', ['rental_id' => $rental->Rental_id])
            ->assertOk()
            ->assertJsonPath('forced_by_admin', true)
            ->assertJsonMissing(['late_fee', 'late_fee_status']);

        $this->assertSame('returned', $rental->fresh()->Rental_status);
        $this->assertSame(0, (int) $user->wallet->fresh()->Wallet_count);
        $this->assertSame(1, (int) $game->fresh()->Bg_use_status);
    }

    private function makeOverdueRental(User $user): Rental_tb
    {
        return Rental_tb::create([
            'User_id' => $user->User_id, 'Bg_id' => $this->makeGame()->Bg_id, 'Rental_cost' => 30,
            'rented_at' => now()->subDays(2), 'due_at' => now()->subMinute(), 'Rental_status' => 'active',
        ]);
    }

    private function makeUser(int $balance, string $role = 'user'): User
    {
        $wallet = Wallet_tb::create(['Wallet_count' => $balance]);

        return User::create([
            'User_username' => 'user'.$wallet->Wallet_id,
            'User_password' => Hash::make('secret12'),
            'User_phone' => null,
            'User_name' => 'Test User',
            'Wallet_id' => $wallet->Wallet_id,
            'User_status' => 1,
            'User_role' => $role,
        ]);
    }

    private function makeGame(): Boardgame_tb
    {
        $category = Boardgame_category_tb::firstOrCreate(['Bg_category_name' => 'วางแผน']);

        return Boardgame_tb::create([
            'Bg_name' => 'Test Game', 'Bg_cost' => 30, 'Bg_min_player' => 2,
            'Bg_max_player' => 4, 'Bg_playduration' => 60,
            'Bg_Catetogory_id' => $category->Bg_category_id, 'Bg_use_status' => 1, 'Bg_Image' => '',
        ]);
    }
}
