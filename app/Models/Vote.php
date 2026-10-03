<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    public const FUNNY = 'funny';
    public const MEH = 'meh';

    protected $fillable = ['answer_id', 'voter_id', 'choice'];

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }
}
