<?php

namespace App\Services\Judging;

use App\Models\Round;
use App\Models\Vote;
use Illuminate\Support\Collection;

/**
 * online_vote: スコア = 面白い票数 - 微妙票数。
 */
class VoteJudgingStrategy implements JudgingStrategy
{
    public function calculateScores(Round $round): Collection
    {
        return $round->answers()->with('votes')->get()->mapWithKeys(fn ($answer) => [
            $answer->user_id => $answer->votes->where('choice', Vote::FUNNY)->count()
                - $answer->votes->where('choice', Vote::MEH)->count(),
        ]);
    }
}
