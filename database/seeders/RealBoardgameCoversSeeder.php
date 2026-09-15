<?php

namespace Database\Seeders;

use App\Models\Boardgame_tb;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RealBoardgameCoversSeeder extends Seeder
{
    public function run(): void
    {
        $items = json_decode(file_get_contents(database_path('data/real-boardgame-covers.json')), true, 512, JSON_THROW_ON_ERROR);
        $disk = Storage::disk('public');
        // Validate every file before updating any database row.
        foreach ($items as $item) {
            $body = $disk->exists($item['path']) ? $disk->get($item['path']) : Http::timeout(30)->get($item['image_url'])->throw()->body();
            if (hash('sha256', $body) !== $item['sha256'] || !@getimagesizefromstring($body)) {
                throw new RuntimeException('Image verification failed: '.$item['name']);
            }
            if (!$disk->exists($item['path']) && !$disk->put($item['path'], $body)) {
                throw new RuntimeException('Cannot store image: '.$item['name']);
            }
        }
        $count = DB::transaction(function () use ($items) {
            $count = 0;
            foreach ($items as $item) {
                foreach (Boardgame_tb::where('Bg_name', $item['name'])->lockForUpdate()->get() as $game) {
                    $target = '/storage/'.$item['path'];
                    // Never replace a later admin upload or touch soft-deleted games.
                    if ($game->Bg_Image === $target) continue;
                    if ($game->Bg_Image !== $item['previous_image'] && !str_starts_with($game->Bg_Image ?? '', 'https://cf.geekdo-images.com/images/')) continue;
                    $game->Bg_Image = $target;
                    $game->save();
                    $count++;
                }
            }
            return $count;
        });
        $this->command?->info("Verified 47 real covers; updated {$count} games.");
    }
}
