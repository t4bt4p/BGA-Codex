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
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationships_load_only_the_matching_user_game_and_category(): void
    {
        Queue::fake();
        $wallet = Wallet_tb::create(['Wallet_count' => 100]);
        $user = User::create(['User_username' => 'model-user', 'User_password' => 'unused', 'User_name' => 'Model User', 'Wallet_id' => $wallet->Wallet_id]);
        $other = User::create(['User_username' => 'other-user', 'User_password' => 'unused', 'User_name' => 'Other User']);
        $category = Boardgame_category_tb::create(['Bg_category_name' => 'Strategy']);
        $otherCategory = Boardgame_category_tb::create(['Bg_category_name' => 'Party']);
        $fields = ['Bg_name' => 'Game', 'Bg_cost' => 10, 'Bg_min_player' => 2, 'Bg_max_player' => 4, 'Bg_playduration' => 30, 'Bg_use_status' => 1, 'Bg_Image' => ''];
        $game = Boardgame_tb::create($fields + ['Bg_Catetogory_id' => $category->Bg_category_id]);
        $otherGame = Boardgame_tb::create($fields + ['Bg_Catetogory_id' => $otherCategory->Bg_category_id]);
        $rentalFields = ['Rental_cost' => 10, 'rented_at' => now(), 'due_at' => now()->addDay()];
        $rental = Rental_tb::create($rentalFields + ['User_id' => $user->User_id, 'Bg_id' => $game->Bg_id]);
        Rental_tb::create($rentalFields + ['User_id' => $other->User_id, 'Bg_id' => $otherGame->Bg_id]);
        $transaction = Transaction_tb::create(['User_id' => $user->User_id, 'Bg_id' => $game->Bg_id, 'Rental_id' => $rental->Rental_id, 'T_cost' => 10, 'T_type' => 'rental_debit']);
        Transaction_tb::create(['User_id' => $other->User_id, 'Bg_id' => $otherGame->Bg_id, 'T_cost' => 10, 'T_type' => 'rental_debit']);
        $topup = Topup_request_tb::create(['User_id' => $user->User_id, 'Amount' => 20, 'Reference' => 'model-topup']);
        Topup_request_tb::create(['User_id' => $other->User_id, 'Amount' => 20, 'Reference' => 'other-topup']);

        $user->load(['rentals', 'transactions', 'topupRequests']);
        $game->load(['rentals', 'transactions']);
        $category->load('boardgames');
        $this->assertSame([$rental->Rental_id], $user->rentals->modelKeys());
        $this->assertSame([$transaction->Ts_id], $user->transactions->modelKeys());
        $this->assertSame([$topup->Topup_id], $user->topupRequests->modelKeys());
        $this->assertSame([$rental->Rental_id], $game->rentals->modelKeys());
        $this->assertSame([$transaction->Ts_id], $game->transactions->modelKeys());
        $this->assertSame([$game->Bg_id], $category->boardgames->modelKeys());
        $this->assertSame(100, $user->wallet->balance());
    }

    public function test_overdue_method_and_attribute_agree_at_deadline_and_after_return(): void
    {
        $this->freezeTime();
        $rental = new Rental_tb(['Rental_status' => 'active', 'due_at' => now()]);
        $this->assertFalse($rental->isOverdue());
        $this->assertFalse($rental->is_overdue);
        $this->assertTrue($rental->isOverdue(now()->addSecond()));
        $rental->due_at = now()->subDay();
        $this->assertTrue($rental->isOverdue());
        $this->assertTrue($rental->is_overdue);
        $rental->Rental_status = 'returned';
        $rental->returned_at = now();
        $this->assertFalse($rental->isOverdue());
        $this->assertFalse($rental->is_overdue);
        $this->assertSame(1, $rental->calculateOverdueDays());
    }
}
