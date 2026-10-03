<?php

namespace App\Events;

use App\Models\Round;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** 結果発表・脱落発表(全員向け) */
class RoundFinished implements ShouldBroadcastNow
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
            'results' => $this->round->results()->with('user:id,name')->orderBy('rank')->get()
                ->map(fn ($r) => [
                    'user_id' => $r->user_id,
                    'name' => $r->user->name,
                    'score' => $r->score,
                    'rank' => $r->rank,
                    'is_eliminated' => $r->is_eliminated,
                ])->all(),
        ];
    }
}
