<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_vote_round_flow(): void
    {
        config(['broadcasting.default' => 'null']);
        $host = User::factory()->create();
        $room = Room::create(['code' => 'ABC123', 'host_id' => $host->id, 'judging_mode' => 'online_vote', 'max_performers' => 2]);
        [$p1, $p2, $judge] = User::factory()->count(3)->create();

        // 入室: 定員2なので3人目は審査員
        foreach ([$p1, $p2, $judge] as $u) {
            $this->actingAs($u)->get('/rooms/ABC123')->assertOk();
        }
        $this->assertSame('judge', $room->members()->where('user_id', $judge->id)->value('role'));

        $this->actingAs($host)->postJson('/rooms/ABC123/rounds', ['title' => 'こんな桃太郎は嫌だ', 'time_limit_sec' => 60])->assertNoContent();
        $this->actingAs($p1)->postJson('/rooms/ABC123/answer', ['body' => 'A'])->assertNoContent();
        $this->actingAs($p2)->postJson('/rooms/ABC123/answer', ['body' => 'B'])->assertNoContent();
        $this->actingAs($judge)->postJson('/rooms/ABC123/answer', ['body' => 'x'])->assertForbidden();

        // 締切前は他人の回答は見えない
        $this->actingAs($judge)->getJson('/rooms/ABC123/state')->assertJsonPath('round.revealed', []);

        $this->actingAs($host)->postJson('/rooms/ABC123/close-answers')->assertNoContent();
        $this->actingAs($host)->postJson('/rooms/ABC123/reveal-next')->assertNoContent();
        $this->actingAs($judge)->postJson('/rooms/ABC123/vote', ['choice' => 'funny'])->assertNoContent();
        $this->actingAs($host)->postJson('/rooms/ABC123/finish-round')->assertStatus(422); // 未公開あり
        $this->actingAs($host)->postJson('/rooms/ABC123/reveal-next')->assertNoContent();
        $this->actingAs($judge)->postJson('/rooms/ABC123/vote', ['choice' => 'meh'])->assertNoContent();
        $this->actingAs($host)->postJson('/rooms/ABC123/finish-round')->assertNoContent();

        $state = $this->actingAs($p1)->getJson('/rooms/ABC123/state')->assertOk()->json();
        $this->assertSame('finished', $state['round']['status']);
        $this->assertCount(2, $state['round']['results']);
        $this->assertSame('finished', $room->fresh()->status); // 演者が1人になったので大会終了
    }
}
