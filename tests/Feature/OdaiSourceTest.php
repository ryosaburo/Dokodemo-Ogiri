<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Services\OdaiSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OdaiSourceTest extends TestCase
{
    use RefreshDatabase;

    private const T = OdaiSource::TOPIC_PREFIX;

    private function html(): string
    {
        $rank = '';
        foreach (['A' => '100', 'B' => '90'] as $t => $v) {
            $rank .= '<li><a href="'.self::T.$t.'/"><span class="views_title">ランキング'.$t.'</span><span class="count">'.$v.',000 ビュー</span></a></li>';
        }
        $recent = '';
        foreach (['C', 'D'] as $t) {
            $recent .= '<li><a class="bbp-forum-title" href="'.self::T.$t.'/">新着'.$t.'</a></li>';
        }

        return '<html><body><ul>'.$rank.'</ul><ul>'.$recent.'</ul>'
            .'<a href="https://example.com/evil/"><span class="views_title">外部リンク</span></a></body></html>';
    }

    public function test_parses_ranking_and_recent_and_ignores_foreign_links(): void
    {
        $titles = array_column((new OdaiSource())->parse($this->html()), 'title');

        $this->assertSame(['ランキングA', 'ランキングB', '新着C', '新着D'], $titles);
    }

    public function test_host_gets_three_candidates_and_can_start_with_source(): void
    {
        Cache::flush();
        Http::fake([OdaiSource::PAGE_URL => Http::response($this->html())]);
        config(['broadcasting.default' => 'null']);
        $host = User::factory()->create();
        [$p1, $p2] = User::factory()->count(2)->create();
        Room::create(['code' => 'ODAI01', 'host_id' => $host->id, 'judging_mode' => 'online_vote', 'max_performers' => 4]);
        foreach ([$p1, $p2] as $p) {
            $this->actingAs($p)->get('/rooms/ODAI01');
        }

        $this->actingAs($p1)->getJson('/rooms/ODAI01/odai-candidates')->assertForbidden();
        $c = $this->actingAs($host)->getJson('/rooms/ODAI01/odai-candidates')->assertOk()->json('candidates');
        $this->assertCount(3, $c);

        $this->actingAs($host)->postJson('/rooms/ODAI01/rounds', ['title' => $c[0]['title'], 'source_url' => $c[0]['url'], 'time_limit_sec' => 30])->assertNoContent();
        $this->actingAs($p1)->getJson('/rooms/ODAI01/state')->assertJsonPath('round.odai.source_url', $c[0]['url']);
    }

    public function test_rejects_foreign_source_url_and_reports_fetch_failure(): void
    {
        Cache::flush();
        Http::fake([OdaiSource::PAGE_URL => Http::response('', 500)]);
        $host = User::factory()->create();
        Room::create(['code' => 'ODAI02', 'host_id' => $host->id, 'judging_mode' => 'online_vote', 'max_performers' => 4]);

        $this->actingAs($host)->getJson('/rooms/ODAI02/odai-candidates')->assertStatus(502)->assertJsonPath('message', fn ($m) => str_contains($m, '取得できません'));
        $this->actingAs($host)->postJson('/rooms/ODAI02/rounds', ['title' => 'x', 'source_url' => 'https://example.com/', 'time_limit_sec' => 30])->assertStatus(422);
    }
}
