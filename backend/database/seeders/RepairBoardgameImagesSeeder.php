<?php

namespace Database\Seeders;

use App\Models\Boardgame_tb;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class RepairBoardgameImagesSeeder extends Seeder
{
    public function run(): void
    {
        $games = Boardgame_tb::query()
            ->where('Bg_Image', 'like', 'https://cf.geekdo-images.com/%')
            ->get();

        foreach ($games as $game) {
            $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', $game->Bg_name), '-');
            $path = "boardgames/generated/{$game->Bg_id}-{$slug}.svg";
            $title = htmlspecialchars($game->Bg_name, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $meta = htmlspecialchars("{$game->Bg_min_player}-{$game->Bg_max_player} players  |  {$game->Bg_playduration} min", ENT_QUOTES | ENT_XML1, 'UTF-8');
            $hue = (int) (($game->Bg_id * 47) % 360);
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="520" viewBox="0 0 800 520" role="img" aria-label="{$title}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="hsl({$hue},72%,34%)"/>
      <stop offset="1" stop-color="hsl({$hue},72%,68%)"/>
    </linearGradient>
    <pattern id="p" width="48" height="48" patternUnits="userSpaceOnUse" patternTransform="rotate(25)">
      <circle cx="12" cy="12" r="5" fill="#fff" opacity=".18"/>
      <circle cx="36" cy="36" r="5" fill="#fff" opacity=".12"/>
    </pattern>
  </defs>
  <rect width="800" height="520" rx="28" fill="url(#bg)"/>
  <rect width="800" height="520" rx="28" fill="url(#p)"/>
  <rect x="42" y="42" width="716" height="436" rx="22" fill="#101827" opacity=".22"/>
  <text x="400" y="205" text-anchor="middle" fill="#fff" font-family="Arial, sans-serif" font-size="42" font-weight="700">BOARD GAME</text>
  <text x="400" y="282" text-anchor="middle" fill="#fff" font-family="Arial, sans-serif" font-size="34" font-weight="700">{$title}</text>
  <text x="400" y="350" text-anchor="middle" fill="#f8fafc" font-family="Arial, sans-serif" font-size="22">{$meta}</text>
  <circle cx="110" cy="110" r="30" fill="#fff" opacity=".9"/>
  <path d="M98 110h24M110 98v24" stroke="hsl({$hue},72%,34%)" stroke-width="7" stroke-linecap="round"/>
</svg>
SVG;
            Storage::disk('public')->put($path, $svg);
            $game->update(['Bg_Image' => '/storage/'.$path]);
        }

        $this->command?->info("Generated {$games->count()} local boardgame covers.");
    }
}
