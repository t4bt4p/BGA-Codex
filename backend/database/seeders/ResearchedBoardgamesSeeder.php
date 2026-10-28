<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ResearchedBoardgamesSeeder extends Seeder
{
    public function run(): void
    {
        // Facts and normalization notes: docs/BOARDGAME_SOURCES.md.
        $games = [
            ['Codenames (2025)', 'codenames', 4, 8, 15, 'ปาร์ตี้', 30],
            ['Galaxy Trucker (2021)', 'galaxy-trucker', 2, 4, 30, 'ปาร์ตี้', 40],
            ['Lost Ruins of Arnak', 'lost-ruins-of-arnak', 1, 4, 120, 'กลยุทธ์', 60],
            ['SETI: Search for Extraterrestrial Intelligence', 'seti-search-for-extraterrestrial-intelligence', 1, 4, 160, 'กลยุทธ์', 80],
        ];
        $rows = [];
        foreach ($games as [$name, $slug, $min, $max, $minutes, $category, $cost]) {
            if (DB::table('Boardgame_tb')->where('Bg_name', $name)->exists()) {
                $this->command?->info("Skipped existing: {$name}");
                continue;
            }
            $page = 'https://www.czechgames.com/games/'.$slug;
            $html = Http::timeout(30)->get($page)->throw()->body();
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $image = (new \DOMXPath($dom))->evaluate('string(//meta[@property="og:image"]/@content)');
            if (parse_url($image, PHP_URL_SCHEME) !== 'https' || parse_url($image, PHP_URL_HOST) !== 'cdn.prod.website-files.com') {
                throw new RuntimeException("Unexpected publisher image for {$name}");
            }
            $bytes = Http::timeout(30)->get($image)->throw()->body();
            $info = @getimagesizefromstring($bytes);
            $extension = ['image/webp' => 'webp', 'image/png' => 'png', 'image/jpeg' => 'jpg'][$info['mime'] ?? ''] ?? null;
            if (! $extension || strlen($bytes) > 10 * 1024 * 1024) {
                throw new RuntimeException("Invalid image for {$name}");
            }
            $path = "boardgames/researched/{$slug}.{$extension}";
            if (! Storage::disk('public')->put($path, $bytes)) {
                throw new RuntimeException("Cannot save {$path}");
            }
            $rows[] = compact('name', 'min', 'max', 'minutes', 'category', 'cost', 'path');
        }
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $categoryId = DB::table('Boardgame_category_tb')->where('Bg_category_name', $row['category'])->value('Bg_category_id');
                if (! $categoryId) {
                    throw new RuntimeException('Missing category: '.$row['category']);
                }
                if (DB::table('Boardgame_tb')->where('Bg_name', $row['name'])->exists()) {
                    continue;
                }
                DB::table('Boardgame_tb')->insert([
                    'Bg_name' => $row['name'], 'Bg_cost' => $row['cost'],
                    'Bg_min_player' => $row['min'], 'Bg_max_player' => $row['max'],
                    'Bg_playduration' => $row['minutes'], 'Bg_Catetogory_id' => $categoryId,
                    'Bg_use_status' => 1, 'Bg_Image' => '/storage/'.$row['path'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        $this->command?->info('Imported '.count($rows).' researched demo games.');
    }
}
