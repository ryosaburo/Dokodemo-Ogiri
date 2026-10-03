<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Room;
use App\Models\Round;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformerRevealTest extends TestCase
{
    use RefreshDatabase;

    private function start(string $mode): array
    {
        config(['broadcasting.default' => 'null']);
        $host = User::factory()->create();
        Room::create(['code' => 'REV001', 'host_id' => $host->id, 'judging_mode' => $mode, 'max_performers' => 3]);
        $ps = User::factory()->count(3)->create();
        $judge = User::factory()->create();
        foreach ([...$ps, $judge] as $u) {
            $this->actingAs($u)->get('/rooms/REV001');
        }
        $this->actingAs($host)->postJson('/rooms/REV001/rounds', ['title' => 't', 'time_limit_sec' => 30])->assertNoContent();
        foreach ($ps as $p) {
            $this->actingAs($p)->postJson('/rooms/REV001/answer', ['body' => 'x'.$p->id])->assertNoContent();
        }
        $this->actingAs($host)->postJson('/rooms/REV001/close-answers')->assertNoContent();

        return [$host, $ps, $judge];
    }

    private function byOrder(int $order): User
    {
        return User::find(Answer::where('reveal_order', $order)->value('user_id'));
    }

    public function test_only_the_next_performer_can_reveal_their_answer(): void
    {
        [$host, , $judge] = $this->start('online_vote');
        $first = $this->byOrder(1);
        $second = $this->byOrder(2);

        $this->assertTrue($this->actingAs($first)->getJson('/rooms/REV001/state')->json('round.next_reveal_is_mine'));
        $this->assertFalse($this->actingAs($second)->getJson('/rooms/REV001/state')->json('round.next_reveal_is_mine'));

        $this->actingAs($second)->postJson('/rooms/REV001/reveal-next')->assertForbidden(); // 順番が違う
        $this->actingAs($judge)->postJson('/rooms/REV001/reveal-next')->assertForbidden();
        $this->actingAs($first)->postJson('/rooms/REV001/reveal-next')->assertNoContent();
        $this->assertNotNull(Answer::where('reveal_order', 1)->value('revealed_at'));

        // 次は2番目の演者。ホストは代行できる
        $this->actingAs($first)->postJson('/rooms/REV001/reveal-next')->assertForbidden();
        $this->actingAs($host)->postJson('/rooms/REV001/reveal-next')->assertNoContent();
        $this->assertNotNull(Answer::where('reveal_order', 2)->value('revealed_at'));
    }

    public function test_host_saves_volume_for_a_revealed_answer_only(): void
    {
        [$host, $ps] = $this->start('offline_laugh');
        $first = $this->byOrder(1);
        $answer = Answer::where('reveal_order', 1)->first();

        $this->actingAs($host)->postJson('/rooms/REV001/volume', ['answer_id' => $answer->id, 'volume_sum' => 5])->assertNotFound(); // 未公開
        $this->actingAs($first)->postJson('/rooms/REV001/reveal-next')->assertNoContent();
        $this->actingAs($ps[0])->postJson('/rooms/REV001/volume', ['answer_id' => $answer->id, 'volume_sum' => 5])->assertForbidden();
        $this->actingAs($host)->postJson('/rooms/REV001/volume', ['answer_id' => $answer->id, 'volume_sum' => 5.5])->assertNoContent();

        $this->assertSame(5.5, $answer->fresh()->raw_volume_sum);
    }
}
