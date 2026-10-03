<?php

namespace App\Events;

use App\Models\Round;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** お題提示(全員向け) */
class RoundStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Round $round) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('room.'.$this->round->room_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'round_id' => $this->round->id,
            'round_number' => $this->round->round_number,
            'odai' => $this->round->odai->only(['title', 'image_path']),
            'time_limit_sec' => $this->round->time_limit_sec,
            'started_at' => $this->round->started_at,
        ];
    }
}
