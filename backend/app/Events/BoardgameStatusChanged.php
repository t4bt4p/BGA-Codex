<?php

namespace App\Events;

use App\Models\Boardgame_tb;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BoardgameStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Boardgame_tb $boardgame) {}

    public function broadcastOn(): array
    {
        return [new Channel('boardgames')];
    }

    public function broadcastAs(): string
    {
        return 'boardgame.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'Bg_id' => $this->boardgame->Bg_id,
            'Bg_use_status' => (int) $this->boardgame->Bg_use_status,
            'updated_at' => $this->boardgame->updated_at?->toIso8601String(),
        ];
    }
}
