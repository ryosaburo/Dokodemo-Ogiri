<?php

namespace App\Events;

use App\Models\Answer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** 回答受理の通知(本人のみ受信。締切まで他人の回答は非公開) */
class AnswerSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Answer $answer) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.'.$this->answer->round->room_id.'.performer.'.$this->answer->user_id)];
    }

    public function broadcastWith(): array
    {
        return ['answer_id' => $this->answer->id, 'body' => $this->answer->body];
    }
}
