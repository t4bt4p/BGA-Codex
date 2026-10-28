<?php

namespace Database\Seeders;

use App\Models\Boardgame_tb;
use App\Models\Rental_tb;
use App\Models\Topup_request_tb;
use App\Models\Transaction_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use App\Services\BlockchainService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MockTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $includeAdminDebit = filter_var(getenv('MOCK_INCLUDE_ADMIN_DEBIT') ?: 'false', FILTER_VALIDATE_BOOLEAN);
        $games = Boardgame_tb::orderBy('Bg_id')->get();
        if ($games->isEmpty()) {
            throw new \RuntimeException('ต้องมีบอร์ดเกมก่อนสร้างธุรกรรมจำลอง');
        }
        // Mock records are written without payment requests or Polygon jobs.
        Transaction_tb::withoutEvents(function () use ($games, $includeAdminDebit) {
            DB::transaction(function () use ($games, $includeAdminDebit) {
                for ($i = 1; $i <= 35; $i++) {
                    $username = sprintf('mock_tx_%02d', $i);
                    if (User::where('User_username', $username)->exists()) {
                        throw new \RuntimeException("พบข้อมูล $username อยู่แล้ว ไม่สร้างรายการซ้ำ");
                    }
                    $game = $games[($i - 1) % $games->count()];
                    $days = ($i - 1) % 7 + 1;
                    $cost = (int) $game->Bg_cost * $days;
                    $debit = $includeAdminDebit ? (($i - 1) % 5 + 1) * 10 : 0;
                    $amount = max(500, (int) ceil(($cost + $debit + 100) / 100) * 100);
                    $at = now()->startOfDay()->subDays(45 - $i)->addHours(9)->addMinutes($i);
                    $returned = $at->copy()->addDays($days)->subHour();
                    $wallet = Wallet_tb::create(['Wallet_count' => $amount - $cost - $debit]);
                    $user = User::create(['User_username' => $username, 'User_password' => Hash::make(Str::random(40)), 'User_name' => sprintf('Mock ผู้ใช้งาน %02d', $i), 'User_phone' => null, 'Wallet_id' => $wallet->Wallet_id, 'User_status' => 1, 'User_role' => 'user']);
                    $rental = Rental_tb::create(['User_id' => $user->User_id, 'Bg_id' => $game->Bg_id, 'Rental_cost' => $cost, 'rental_days' => $days, 'daily_rate' => $game->Bg_cost, 'rented_at' => $at, 'due_at' => $at->copy()->addDays($days), 'returned_at' => $returned, 'Rental_status' => 'returned']);
                    $rental->forceFill(['created_at' => $at, 'updated_at' => $returned, 'returned_by_user_id' => $user->User_id, 'returned_by_name' => $user->User_name])->save();
                    $topupAt = $at->copy()->subHour();
                    $topup = Topup_request_tb::create(['User_id' => $user->User_id, 'Amount' => $amount, 'Reference' => sprintf('MOCK-TX-TOPUP-%02d', $i), 'Status' => 'approved', 'approved_at' => $topupAt]);
                    $topup->forceFill(['created_at' => $topupAt, 'updated_at' => $topupAt])->save();
                    $records = [
                        ['topup_credit', $amount, $topupAt, false],
                        ['rental_debit', $cost, $at, true],
                        ['return_event', 0, $returned, true],
                    ];
                    if ($includeAdminDebit) {
                        $records[] = ['admin_debit', $debit, $returned->copy()->addHour(), false];
                    }
                    foreach ($records as [$type, $value, $date, $hasRental]) {
                        $tx = new Transaction_tb(['User_id' => $user->User_id, 'Bg_id' => $hasRental ? $game->Bg_id : null, 'Rental_id' => $hasRental ? $rental->Rental_id : null, 'T_cost' => $value, 'T_type' => $type]);
                        $tx->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
                    }
                }
            });
        });
        // Keep the private ledger in sync; no external anchoring is dispatched.
        $ledger = app(BlockchainService::class);
        Transaction_tb::whereHas('user', fn ($query) => $query->where('User_username', 'like', 'mock_tx_%'))
            ->orderBy('created_at')->orderBy('Ts_id')->each(fn ($tx) => $ledger->syncTransaction($tx));
        $this->command->info('สร้าง Mock ธุรกรรมประเภทละ 35 รายการแล้ว');
    }
}
