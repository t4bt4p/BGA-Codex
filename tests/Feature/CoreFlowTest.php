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

    public function test_overdue_return_charges_one_full_rental_price_per_started_day(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');
        $user = $this->makeUser(100);
        $game = $this->makeGame();
        $rental = Rental_tb::create([
            'User_id' => $user->User_id, 'Bg_id' => $game->Bg_id, 'Rental_cost' => 30,
            'rented_at' => Carbon::now()->subDays(9), 'due_at' => Carbon::now()->subDay()->subHour(),
            'Rental_status' => 'active',
        ]);
        $game->update(['Bg_use_status' => 0]);
        app(BlockchainService::class)->addTransaction(['type' => 'topup_credit', 'user_id' => $user->User_id, 'cost' => 100]);
        Sanctum::actingAs($user);

        $this->postJson('/api/rentals/return', ['rental_id' => $rental->Rental_id])
            ->assertOk()->assertJsonPath('late_fee', 60)->assertJsonPath('balance', 40);

        $this->assertDatabaseHas('Rental_tb', ['Rental_id' => $rental->Rental_id, 'late_fee' => 60, 'Rental_status' => 'returned']);
        $this->assertDatabaseHas('Wallet_tb', ['Wallet_id' => $user->Wallet_id, 'Wallet_count' => 40]);
        Carbon::setTestNow();
    }

    public function test_overdue_game_cannot_be_returned_when_wallet_cannot_pay_the_fine(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');
        $user = $this->makeUser(20);
        $game = $this->makeGame();
        $rental = Rental_tb::create([
            'User_id' => $user->User_id, 'Bg_id' => $game->Bg_id, 'Rental_cost' => 30,
            'rented_at' => Carbon::now()->subDays(8), 'due_at' => Carbon::now()->subMinute(),
            'Rental_status' => 'active',
        ]);
        $game->update(['Bg_use_status' => 0]);
        Sanctum::actingAs($user);

        $this->postJson('/api/rentals/return', ['rental_id' => $rental->Rental_id])->assertUnprocessable();

        $this->assertDatabaseHas('Rental_tb', ['Rental_id' => $rental->Rental_id, 'Rental_status' => 'active']);
        $this->assertDatabaseHas('Wallet_tb', ['Wallet_id' => $user->Wallet_id, 'Wallet_count' => 20]);
        Carbon::setTestNow();
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
