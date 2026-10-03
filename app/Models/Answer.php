<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Answer extends Model
{
    protected $fillable = [
        'round_id', 'user_id', 'body', 'submitted_at', 'reveal_order', 'revealed_at',
        'raw_volume_sum', 'normalized_score',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'revealed_at' => 'datetime'];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
