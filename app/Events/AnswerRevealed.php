<?php

namespace App\Events;

use App\Models\Answer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** 回答をランダム順に1件ずつ公開(全員向け) */
class AnswerRevealed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Answer $answer) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('room.'.$this->answer->round->room_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'answer_id' => $this->answer->id,
            'body' => $this->answer->body,
            'reveal_order' => $this->answer->reveal_order,
            'revealed_at' => $this->answer->revealed_at,
        ];
    }
}
