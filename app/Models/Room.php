<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    public const MODE_OFFLINE = 'offline_laugh';
    public const MODE_ONLINE = 'online_vote';

    protected $fillable = ['code', 'host_id', 'status', 'max_performers', 'max_rounds', 'judging_mode'];

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(RoomMember::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }
}
