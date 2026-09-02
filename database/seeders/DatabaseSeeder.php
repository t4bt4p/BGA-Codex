<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();
        if ($password = env('ADMIN_PASSWORD')) {
            $walletId = DB::table('Wallet_tb')->insertGetId([
                'Wallet_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'Wallet_id');
            DB::table('User_tb')->insert([
                'User_username' => env('ADMIN_USERNAME', 'admin'),
                'User_password' => Hash::make($password),
                'User_phone' => null,
                'User_name' => env('ADMIN_NAME', 'ผู้ดูแลระบบ'),
                'Wallet_id' => $walletId,
                'User_status' => 1,
                'User_role' => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categories = collect(['ครอบครัว', 'ปริศนา', 'ปาร์ตี้', 'กลยุทธ์'])
            ->mapWithKeys(function (string $name) use ($now) {
                $id = DB::table('Boardgame_category_tb')->insertGetId([
                    'Bg_category_name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'Bg_category_id');

                return [$name => $id];
            });

        foreach ([
            ['ป่ามหัศจรรย์', 40, 2, 5, 35, 'ครอบครัว'],
            ['คดีลับคืนจันทร์', 60, 3, 6, 75, 'ปริศนา'],
            ['ตลาดนัดมหาสนุก', 30, 3, 8, 25, 'ปาร์ตี้'],
            ['ผู้บุกเบิกกาแล็กซี', 80, 2, 4, 90, 'กลยุทธ์'],
            ['คำต้องห้าม', 25, 4, 10, 20, 'ปาร์ตี้'],
        ] as [$name, $cost, $min, $max, $duration, $category]) {
            DB::table('Boardgame_tb')->insert([
                'Bg_name' => $name,
                'Bg_cost' => $cost,
                'Bg_min_player' => $min,
                'Bg_max_player' => $max,
                'Bg_playduration' => $duration,
                'Bg_Catetogory_id' => $categories[$category],
                'Bg_use_status' => 1,
                'Bg_Image' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
