<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundResult extends Model
{
    protected $fillable = ['round_id', 'user_id', 'score', 'rank', 'is_eliminated'];

    protected function casts(): array
    {
        return ['is_eliminated' => 'boolean'];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
