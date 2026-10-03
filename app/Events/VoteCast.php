<?php

namespace App\Events;

use App\Models\Vote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** 投票通知(online_voteモード)。投票者・選択内容は含めず件数のみ通知 */
class VoteCast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Vote $vote) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('room.'.$this->vote->answer->round->room_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'answer_id' => $this->vote->answer_id,
            'total_votes' => $this->vote->answer->votes()->count(),
        ];
    }
}
