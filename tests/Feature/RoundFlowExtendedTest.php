<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundFlowExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function setupRoom(string $mode, int $performers, ?int $maxRounds = null): array
    {
        config(['broadcasting.default' => 'null']);
        $host = User::factory()->create();
        $room = Room::create(['code' => 'EXT001', 'host_id' => $host->id, 'judging_mode' => $mode, 'max_performers' => $performers, 'max_rounds' => $maxRounds]);
        $ps = User::factory()->count($performers)->create();
        foreach ($ps as $p) {
            $this->actingAs($p)->get('/rooms/EXT001')->assertOk();
        }

        return [$host, $ps->all(), $room];
    }

    private function startAndAnswer(User $host, array $performers, array $bodies): void
    {
        $this->actingAs($host)->postJson('/rooms/EXT001/rounds', ['title' => 'お題', 'time_limit_sec' => 30])->assertNoContent();
        foreach ($bodies as $i => $body) {
            $this->actingAs($performers[$i])->postJson('/rooms/EXT001/answer', ['body' => $body])->assertNoContent();
        }
        $this->actingAs($host)->postJson('/rooms/EXT001/close-answers')->assertNoContent();
    }

    /** 公開順はランダムなので、現在公開中の回答の作者に音量を割り当てる */
    private function revealAll(User $host, array $volumeByUserId): void
    {
        $prev = null;
        while (true) {
            $payload = $prev ? ['volume_sum' => $volumeByUserId[$prev]] : [];
            $res = $this->actingAs($host)->postJson('/rooms/EXT001/reveal-next', $payload);
            if ($res->status() === 422) {
                break;
            }
            $prev = \App\Models\Answer::where('round_id', \App\Models\Round::max('id'))->whereNotNull('revealed_at')->orderByDesc('reveal_order')->first()->user_id;
        }
        $this->actingAs($host)->postJson('/rooms/EXT001/finish-round', ['volume_sum' => $volumeByUserId[$prev]])->assertNoContent();
    }

    public function test_offline_laugh_mode_scores_by_volume_and_eliminates_lowest(): void
    {
        [$host, $ps] = $this->setupRoom('offline_laugh', 3);
        $this->startAndAnswer($host, $ps, ['a', 'b', 'c']);
        $this->revealAll($host, [$ps[0]->id => 5.0, $ps[1]->id => 20.0, $ps[2]->id => 10.0]);

        $res = $this->actingAs($ps[0])->getJson('/rooms/EXT001/state')->json('round.results');
        $this->assertSame([100, 50, 25], array_column($res, 'score'));
        $this->assertSame([false, false, true], array_column($res, 'is_eliminated'));
    }

    public function test_answers_are_auto_closed_after_deadline(): void
    {
        [$host, $ps] = $this->setupRoom('online_vote', 2);
        $this->actingAs($host)->postJson('/rooms/EXT001/rounds', ['title' => 'お題', 'time_limit_sec' => 30])->assertNoContent();
        $this->actingAs($ps[0])->postJson('/rooms/EXT001/answer', ['body' => 'a'])->assertNoContent();

        $this->travel(40)->seconds();
        $this->actingAs($ps[1])->postJson('/rooms/EXT001/answer', ['body' => 'late'])->assertStatus(422);
        $this->actingAs($ps[0])->getJson('/rooms/EXT001/state')->assertJsonPath('round.status', 'voting');
    }

    public function test_multiple_rounds_until_one_performer_remains(): void
    {
        [$host, $ps, $room] = $this->setupRoom('offline_laugh', 3);

        $this->startAndAnswer($host, $ps, ['a', 'b', 'c']);
        $this->revealAll($host, [$ps[0]->id => 30.0, $ps[1]->id => 20.0, $ps[2]->id => 10.0]);
        $this->assertSame('in_progress', $room->fresh()->status);

        // 脱落した演者は次のラウンドで回答できない
        $this->actingAs($host)->postJson('/rooms/EXT001/rounds', ['title' => '2', 'time_limit_sec' => 30])->assertNoContent();
        $this->actingAs($ps[2])->postJson('/rooms/EXT001/answer', ['body' => 'x'])->assertForbidden();
        $this->actingAs($ps[0])->postJson('/rooms/EXT001/answer', ['body' => 'a'])->assertNoContent();
        $this->actingAs($ps[1])->postJson('/rooms/EXT001/answer', ['body' => 'b'])->assertNoContent();
        $this->actingAs($host)->postJson('/rooms/EXT001/close-answers')->assertNoContent();
        $this->revealAll($host, [$ps[0]->id => 30.0, $ps[1]->id => 10.0]);

        $this->assertSame('finished', $room->fresh()->status);
        $this->actingAs($host)->postJson('/rooms/EXT001/rounds', ['title' => '3', 'time_limit_sec' => 30])->assertStatus(422);
    }

    public function test_room_ends_at_max_rounds(): void
    {
        [$host, $ps, $room] = $this->setupRoom('offline_laugh', 3, maxRounds: 1);
        $this->startAndAnswer($host, $ps, ['a', 'b', 'c']);
        $this->revealAll($host, [$ps[0]->id => 30.0, $ps[1]->id => 20.0, $ps[2]->id => 10.0]);

        $this->assertSame('finished', $room->fresh()->status);
    }

    public function test_permissions(): void
    {
        [$host, $ps] = $this->setupRoom('online_vote', 2);
        $judge = User::factory()->create();
        $this->actingAs($judge)->get('/rooms/EXT001')->assertOk();

        $this->actingAs($ps[0])->postJson('/rooms/EXT001/rounds', ['title' => 'x', 'time_limit_sec' => 30])->assertForbidden();
        $this->actingAs($judge)->postJson('/rooms/EXT001/close-answers')->assertForbidden();

        $this->startAndAnswer($host, $ps, ['a', 'b']);
        $this->actingAs($host)->postJson('/rooms/EXT001/reveal-next')->assertNoContent();
        $this->actingAs($ps[0])->postJson('/rooms/EXT001/vote', ['choice' => 'funny'])->assertForbidden(); // 演者は投票不可
        $this->actingAs($judge)->postJson('/rooms/EXT001/vote', ['choice' => 'invalid'])->assertStatus(422);
        $this->actingAs($judge)->postJson('/rooms/EXT001/vote', ['choice' => 'funny'])->assertNoContent();
    }
}
