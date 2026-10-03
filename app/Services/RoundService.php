<?php

namespace App\Services;

use App\Events\RoundFinished;
use App\Models\Room;
use App\Models\Round;
use App\Models\RoundResult;
use App\Services\Judging\JudgingStrategy;
use App\Services\Judging\LaughJudgingStrategy;
use App\Services\Judging\VoteJudgingStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoundService
{
    public function strategyFor(Room $room): JudgingStrategy
    {
        return match ($room->judging_mode) {
            Room::MODE_OFFLINE => new LaughJudgingStrategy(),
            Room::MODE_ONLINE => new VoteJudgingStrategy(),
        };
    }

    /**
     * 全回答の公開・審査後に呼ぶ。スコア集計 → 順位 → 脱落判定 → round_results 保存。
     *
     * 順位・脱落のルール(仕様書10章の決定事項):
     * - 同点は同順位(1位,1位,3位…)。
     * - 未回答の演者は0点・最下位扱いで脱落する(回答者が1人以上いる場合)。
     * - 未回答者がいなければ、最下位の得点の演者を全員脱落させる(同点最下位は全員脱落)。
     * - 全員が同点(全員0点・同数票を含む)なら誰も脱落しない。ホストが次のラウンドを行う。
     */
    public function finish(Round $round): void
    {
        DB::transaction(function () use ($round) {
            $scores = $this->strategyFor($round->room)->calculateScores($round);
            $active = $round->room->members()
                ->where('role', 'performer')->where('is_eliminated', false)->pluck('user_id');

            $silent = $active->diff($scores->keys())->values();
            $eliminated = $this->decideEliminated($scores, $silent);

            foreach ($this->rank($scores) as $userId => $rank) {
                RoundResult::updateOrCreate(
                    ['round_id' => $round->id, 'user_id' => $userId],
                    ['score' => $scores[$userId], 'rank' => $rank, 'is_eliminated' => $eliminated->contains($userId)],
                );
            }

            $lastRank = $scores->count() + 1;
            foreach ($silent as $userId) {
                RoundResult::updateOrCreate(
                    ['round_id' => $round->id, 'user_id' => $userId],
                    ['score' => 0, 'rank' => $lastRank, 'is_eliminated' => $eliminated->contains($userId)],
                );
            }

            $round->room->members()->whereIn('user_id', $eliminated)->update(['is_eliminated' => true]);
            $round->update(['status' => 'finished']);
        });

        // 呼び出し元のトランザクションがコミットされてから通知する(受信側がstateを取り直すため)
        DB::afterCommit(fn () => RoundFinished::dispatch($round));
    }

    /** @return Collection<int, int> [user_id => rank] 同点は同順位 */
    public function rank(Collection $scores): Collection
    {
        $sorted = $scores->sortDesc();
        $ranks = collect();
        $prev = null;
        $rank = 0;
        foreach ($sorted->values() as $i => $score) {
            if ($score !== $prev) {
                $rank = $i + 1;
                $prev = $score;
            }
            $ranks[$sorted->keys()[$i]] = $rank;
        }

        return $ranks;
    }

    /** @return Collection<int, int> 脱落するuser_id */
    public function decideEliminated(Collection $scores, Collection $silent): Collection
    {
        if ($scores->isEmpty()) {
            return collect(); // 誰も回答しなかった
        }
        if ($silent->isNotEmpty()) {
            return $silent;
        }
        if ($scores->unique()->count() === 1) {
            return collect(); // 全員同点
        }

        $lowest = $scores->min();

        return $scores->filter(fn ($s) => $s === $lowest)->keys();
    }
}
