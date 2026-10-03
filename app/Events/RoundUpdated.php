<?php

namespace App\Events;

use App\Models\Round;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** ラウンド状態の変化通知(全員向け。受信側はstateを再取得する) */
class RoundUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Round $round) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('room.'.$this->round->room_id)];
    }

    public function broadcastWith(): array
    {
        return ['round_id' => $this->round->id, 'status' => $this->round->status];
    }
}
