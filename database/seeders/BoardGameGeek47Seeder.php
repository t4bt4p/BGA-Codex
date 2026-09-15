<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BoardGameGeek47Seeder extends Seeder
{
    public function run(): void
    {
        // Source snapshot: TidyTuesday board_games.csv, originally collected from BGG.
        // Image URLs are retained as source URLs; attribution is documented below.
        $games = [
            ['Pandemic', 2, 4, 45, 'ครอบครัว', 40, 'https://cf.geekdo-images.com/images/pic1534148.jpg'],
            ['Dominion', 2, 4, 30, 'กลยุทธ์', 40, 'https://cf.geekdo-images.com/images/pic394356.jpg'],
            ['7 Wonders', 2, 7, 30, 'กลยุทธ์', 50, 'https://cf.geekdo-images.com/images/pic860217.jpg'],
            ['Agricola', 1, 5, 90, 'กลยุทธ์', 70, 'https://cf.geekdo-images.com/images/pic259085.jpg'],
            ['Ticket to Ride', 2, 5, 60, 'ครอบครัว', 50, 'https://cf.geekdo-images.com/images/pic38668.jpg'],
            ['Puerto Rico', 2, 5, 120, 'กลยุทธ์', 80, 'https://cf.geekdo-images.com/images/pic158548.jpg'],
            ['Small World', 2, 5, 60, 'กลยุทธ์', 60, 'https://cf.geekdo-images.com/images/pic428828.jpg'],
            ['Power Grid', 2, 6, 120, 'กลยุทธ์', 80, 'https://cf.geekdo-images.com/images/pic173153.jpg'],
            ['Citadels', 2, 8, 45, 'ปาร์ตี้', 35, 'https://cf.geekdo-images.com/images/pic3239104.jpg'],
            ['King of Tokyo', 2, 6, 30, 'ปาร์ตี้', 40, 'https://cf.geekdo-images.com/images/pic3043734.jpg'],
            ['Love Letter', 2, 4, 20, 'ปาร์ตี้', 25, 'https://cf.geekdo-images.com/images/pic1401448.jpg'],
            ['Ticket to Ride: Europe', 2, 5, 60, 'ครอบครัว', 55, 'https://cf.geekdo-images.com/images/pic66668.jpg'],
            ['Race for the Galaxy', 2, 4, 60, 'กลยุทธ์', 50, 'https://cf.geekdo-images.com/images/pic236327.jpg'],
            ['Dixit', 3, 6, 30, 'ปาร์ตี้', 40, 'https://cf.geekdo-images.com/images/pic3483909.jpg'],
            ['Stone Age', 2, 4, 75, 'กลยุทธ์', 55, 'https://cf.geekdo-images.com/images/pic1632539.jpg'],
            ['Arkham Horror', 1, 8, 180, 'ปริศนา', 90, 'https://cf.geekdo-images.com/images/pic175966.jpg'],
            ['Codenames', 2, 8, 15, 'ปาร์ตี้', 30, 'https://cf.geekdo-images.com/images/pic2582929.jpg'],
            ['Splendor', 2, 4, 30, 'กลยุทธ์', 40, 'https://cf.geekdo-images.com/images/pic1904079.jpg'],
            ['Lords of Waterdeep', 2, 5, 90, 'กลยุทธ์', 65, 'https://cf.geekdo-images.com/images/pic1116080.jpg'],
            ['Munchkin', 3, 6, 90, 'ปาร์ตี้', 45, 'https://cf.geekdo-images.com/images/pic2790787.jpg'],
            ['Bohnanza', 2, 7, 45, 'ปาร์ตี้', 30, 'https://cf.geekdo-images.com/images/pic69366.jpg'],
            ['Forbidden Island', 2, 4, 30, 'ครอบครัว', 35, 'https://cf.geekdo-images.com/images/pic646458.jpg'],
            ['The Resistance', 5, 10, 30, 'ปาร์ตี้', 35, 'https://cf.geekdo-images.com/images/pic2576459.jpg'],
            ['Battlestar Galactica', 3, 6, 210, 'ปริศนา', 100, 'https://cf.geekdo-images.com/images/pic354500.jpg'],
            ['The Castles of Burgundy', 2, 4, 90, 'กลยุทธ์', 70, 'https://cf.geekdo-images.com/images/pic1176894.jpg'],
            ['Hanabi', 2, 5, 25, 'ครอบครัว', 25, 'https://cf.geekdo-images.com/images/pic2007286.jpg'],
            ['Terra Mystica', 2, 5, 120, 'กลยุทธ์', 90, 'https://cf.geekdo-images.com/images/pic1356616.jpg'],
            ['Dominion: Intrigue', 2, 4, 30, 'กลยุทธ์', 40, 'https://cf.geekdo-images.com/images/pic460011.jpg'],
            ['Dead of Winter: A Crossroads Game', 2, 5, 120, 'ปริศนา', 80, 'https://cf.geekdo-images.com/images/pic3016500.jpg'],
            ['Risk', 2, 6, 120, 'กลยุทธ์', 50, 'https://cf.geekdo-images.com/images/pic2920766.jpg'],
            ['Caylus', 2, 5, 120, 'กลยุทธ์', 70, 'https://cf.geekdo-images.com/images/pic1638795.jpg'],
            ['Betrayal at House on the Hill', 3, 6, 60, 'ปริศนา', 60, 'https://cf.geekdo-images.com/images/pic828598.jpg'],
            ['Alhambra', 2, 6, 60, 'กลยุทธ์', 50, 'https://cf.geekdo-images.com/images/pic1502118.jpg'],
            ['Coup', 2, 6, 15, 'ปาร์ตี้', 25, 'https://cf.geekdo-images.com/images/pic2016054.jpg'],
            ['Galaxy Trucker', 2, 4, 60, 'ปาร์ตี้', 50, 'https://cf.geekdo-images.com/images/pic289193.jpg'],
            ['Eclipse', 2, 6, 180, 'กลยุทธ์', 100, 'https://cf.geekdo-images.com/images/pic1974056.jpg'],
            ['Shadows over Camelot', 3, 7, 75, 'ปริศนา', 65, 'https://cf.geekdo-images.com/images/pic70547.jpg'],
            ['Tigris & Euphrates', 2, 4, 90, 'กลยุทธ์', 70, 'https://cf.geekdo-images.com/images/pic2338267.jpg'],
            ['Takenoko', 2, 4, 45, 'ครอบครัว', 45, 'https://cf.geekdo-images.com/images/pic1912529.jpg'],
            ['BANG!', 4, 7, 30, 'ปาร์ตี้', 30, 'https://cf.geekdo-images.com/images/pic1170986.jpg'],
            ['RoboRally', 2, 8, 90, 'ปาร์ตี้', 50, 'https://cf.geekdo-images.com/images/pic1000553.jpg'],
            ["Memoir '44", 2, 6, 45, 'ปริศนา', 70, 'https://cf.geekdo-images.com/images/pic43663.jpg'],
            ['Le Havre', 1, 5, 120, 'กลยุทธ์', 80, 'https://cf.geekdo-images.com/images/pic3330230.jpg'],
            ['Cosmic Encounter', 3, 5, 90, 'ปาร์ตี้', 60, 'https://cf.geekdo-images.com/images/pic1521633.jpg'],
            ['San Juan', 2, 4, 50, 'กลยุทธ์', 40, 'https://cf.geekdo-images.com/images/pic174174.jpg'],
            ['El Grande', 2, 5, 90, 'กลยุทธ์', 70, 'https://cf.geekdo-images.com/images/pic180538.jpg'],
            ['Mage Knight Board Game', 1, 4, 150, 'ปริศนา', 100, 'https://cf.geekdo-images.com/images/pic1083380.jpg'],
        ];

        $categories = DB::table('Boardgame_category_tb')->pluck('Bg_category_id', 'Bg_category_name');
        foreach ($games as [$name, $min, $max, $duration, $category, $cost, $image]) {
            if (DB::table('Boardgame_tb')->where('Bg_name', $name)->exists()) {
                continue;
            }
            DB::table('Boardgame_tb')->insert([
                'Bg_name' => $name,
                'Bg_cost' => $cost,
                'Bg_min_player' => $min,
                'Bg_max_player' => $max,
                'Bg_playduration' => $duration,
                'Bg_Catetogory_id' => $categories[$category],
                'Bg_use_status' => 1,
                'Bg_Image' => $image,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command?->info('Imported 47 BoardGameGeek games.');
    }
}
