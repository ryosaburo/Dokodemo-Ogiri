<?php

use App\Models\Room;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// 入退室・参加者一覧(presence)
Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    $room = Room::find($roomId);
    if ($room?->host_id === $user->id) {
        return ['id' => $user->id, 'name' => $user->name, 'role' => 'host'];
    }

    $member = $room?->members()->where('user_id', $user->id)->first();

    return $member ? ['id' => $user->id, 'name' => $user->name, 'role' => $member->role] : false;
});

// 演者本人だけが購読できる回答入力用チャンネル
Broadcast::channel('room.{roomId}.performer.{userId}', function ($user, $roomId, $userId) {
    return (int) $user->id === (int) $userId
        && Room::find($roomId)?->members()->where('user_id', $user->id)->where('role', 'performer')->exists();
});
