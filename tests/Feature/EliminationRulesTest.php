<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Odai;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\Round;
use App\Models\User;
use App\Services\RoundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EliminationRulesTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int|string, float|null> $volumes 回答者ごとの音量合計(nullは未回答) */
    private function finishRound(array $volumes): array
    {
        config(['broadcasting.default' => 'null']);
        $host = User::factory()->create();
        $room = Room::create(['code' => strtoupper(substr(uniqid(), -8)), 'host_id' => $host->id, 'judging_mode' => 'offline_laugh', 'max_performers' => 8]);
        $round = Round::create(['room_id' => $room->id, 'odai_id' => Odai::create(['title' => 't'])->id, 'round_number' => 1]);

        $users = [];
        foreach ($volumes as $i => $v) {
            $u = User::factory()->create();
            RoomMember::create(['room_id' => $room->id, 'user_id' => $u->id, 'role' => 'performer']);
            if ($v !== null) {
                Answer::create(['round_id' => $round->id, 'user_id' => $u->id, 'body' => 'x', 'raw_volume_sum' => $v]);
            }
            $users[$i] = $u->id;
        }

        app(RoundService::class)->finish($round);

        $res = $round->results()->get()->keyBy('user_id');

        return array_map(fn ($id) => ['rank' => $res[$id]->rank, 'out' => $res[$id]->is_eliminated, 'score' => $res[$id]->score], $users);
    }

    public function test_lowest_is_eliminated_and_ties_share_rank(): void
    {
        $r = $this->finishRound([10, 40, 40, 20]);
        $this->assertSame([4, 1, 1, 3], array_column($r, 'rank'));
        $this->assertSame([true, false, false, false], array_map(fn ($x) => $x['out'], $r));
    }

    public function test_tied_lowest_are_all_eliminated(): void
    {
        $r = $this->finishRound([5, 5, 40]);
        $this->assertSame([true, true, false], array_map(fn ($x) => $x['out'], $r));
    }

    public function test_everyone_zero_means_no_elimination(): void
    {
        $r = $this->finishRound([0, 0, 0]);
        $this->assertSame([false, false, false], array_map(fn ($x) => $x['out'], $r));
    }

    public function test_non_submitter_is_eliminated_instead_of_lowest_scorer(): void
    {
        $r = $this->finishRound([10, 40, null]);
        $this->assertSame([false, false, true], array_map(fn ($x) => $x['out'], $r));
        $this->assertSame(3, $r[2]['rank']);
    }

    public function test_nobody_answered_means_no_elimination(): void
    {
        $r = $this->finishRound([null, null]);
        $this->assertSame([false, false], array_map(fn ($x) => $x['out'], $r));
    }
}
