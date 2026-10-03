<?php

namespace App\Services\Judging;

use App\Models\Round;
use Illuminate\Support\Collection;

/**
 * offline_laugh: raw_volume_sum をラウンド内最大値=100点に正規化する。
 */
class LaughJudgingStrategy implements JudgingStrategy
{
    public function calculateScores(Round $round): Collection
    {
        $answers = $round->answers()->get();
        $max = (float) $answers->max('raw_volume_sum');

        // 全員0(誰も笑わなかった/マイク未使用)の場合は全員0点(同点)。脱落なしの扱いは RoundService 側で決める。
        return $answers->mapWithKeys(function ($answer) use ($max) {
            $score = $max > 0 ? (int) round($answer->raw_volume_sum / $max * 100) : 0;
            $answer->update(['normalized_score' => $score]);

            return [$answer->user_id => $score];
        });
    }
}
