<?php

namespace App\Services\Judging;

use App\Models\Round;
use Illuminate\Support\Collection;

interface JudgingStrategy
{
    /**
     * @return Collection<int, int> [user_id => score]
     */
    public function calculateScores(Round $round): Collection;
}
