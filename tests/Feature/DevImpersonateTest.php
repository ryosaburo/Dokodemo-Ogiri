<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevImpersonateTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabs_act_as_different_users_without_session(): void
    {
        $this->app['env'] = 'local';
        [$host, $p1, $j] = User::factory()->count(3)->create();
        $room = Room::create(['code' => 'DEV111', 'host_id' => $host->id, 'judging_mode' => 'online_vote', 'max_performers' => 1]);

        $this->get("/rooms/DEV111?as={$host->id}")->assertOk();
        $this->getJson('/rooms/DEV111/state', ['X-Dev-User' => $p1->id])->assertJsonPath('me.role', 'performer');
        $this->getJson('/rooms/DEV111/state', ['X-Dev-User' => $j->id])->assertJsonPath('me.role', 'judge');
        $this->getJson('/rooms/DEV111/state', ['X-Dev-User' => $host->id])->assertJsonPath('me.role', 'host');

    }
}
