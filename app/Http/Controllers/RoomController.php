<?php

namespace App\Http\Controllers;

use App\Events\AnswerRevealed;
use App\Events\AnswerSubmitted;
use App\Events\RoundStarted;
use App\Events\RoundUpdated;
use App\Events\VoteCast;
use App\Models\Answer;
use App\Models\Odai;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\Round;
use App\Models\Vote;
use App\Services\OdaiSource;
use App\Services\RoundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'judging_mode' => ['required', Rule::in([Room::MODE_OFFLINE, Room::MODE_ONLINE])],
            'max_performers' => ['required', 'integer', 'min:2', 'max:8'],
            'max_rounds' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        do {
            $code = strtoupper(Str::random(6));
        } while (Room::where('code', $code)->exists());

        $room = Room::create($data + ['code' => $code, 'host_id' => $request->user()->id]);

        return redirect()->route('rooms.show', array_filter(['code' => $room->code, 'as' => $request->input('as')]));
    }

    public function join(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $room = Room::where('code', strtoupper(trim($data['code'])))->first();

        if (! $room) {
            throw ValidationException::withMessages(['code' => 'ルームが見つかりません']);
        }

        return redirect()->route('rooms.show', array_filter(['code' => $room->code, 'as' => $request->input('as')]));
    }

    public function show(Request $request, string $code)
    {
        $room = $this->room($code);
        $this->ensureMember($room, $request);

        return view('room', ['room' => $room, 'role' => $this->roleOf($room, $request)]);
    }

    public function state(Request $request, string $code): JsonResponse
    {
        $room = $this->room($code);
        $this->ensureMember($room, $request);
        $me = $request->user();
        $role = $this->roleOf($room, $request);
        $round = $room->rounds()->with('odai')->latest('round_number')->first();
        if ($round && $this->autoCloseIfExpired($round)) {
            $round->refresh();
        }

        $members = $room->members()->with('user:id,name')->get()->map(fn ($m) => [
            'id' => $m->user_id,
            'name' => $m->user->name,
            'role' => $m->role,
            'is_eliminated' => $m->is_eliminated,
        ])->prepend(['id' => $room->host_id, 'name' => $room->host->name, 'role' => 'host', 'is_eliminated' => false]);

        $payload = [
            'room' => $room->only(['code', 'status', 'judging_mode', 'max_performers', 'max_rounds']),
            'me' => ['id' => $me->id, 'role' => $role, 'is_eliminated' => (bool) $members->firstWhere('id', $me->id)['is_eliminated']],
            'members' => $members->values(),
            'round' => null,
        ];

        if ($round) {
            $answers = $round->answers()->get();
            $revealed = $answers->whereNotNull('revealed_at')->sortBy('reveal_order')->values();
            $current = $revealed->last();
            $finished = $round->status === 'finished';
            $mine = $answers->firstWhere('user_id', $me->id);

            $payload['round'] = [
                'id' => $round->id,
                'number' => $round->round_number,
                'status' => $round->status,
                'odai' => $round->odai->only(['title', 'source_url']),
                'ends_at' => $round->started_at?->addSeconds($round->time_limit_sec)->toIso8601String(),
                'time_limit' => $round->time_limit_sec,
                'answer_count' => $answers->count(),
                'reveal_total' => $answers->whereNotNull('reveal_order')->count(),
                'my_answer' => $mine?->body,
                'next_reveal_is_mine' => in_array($round->status, ['revealing', 'voting'], true)
                    && $answers->whereNull('revealed_at')->whereNotNull('reveal_order')->sortBy('reveal_order')->first()?->user_id === $me->id,
                'current_answer_id' => $round->status === 'finished' ? null : $current?->id,
                'current_vote_count' => $current && $room->judging_mode === Room::MODE_ONLINE ? $current->votes()->count() : null,
                'my_vote' => $current ? Vote::where('answer_id', $current->id)->where('voter_id', $me->id)->value('choice') : null,
                'revealed' => $revealed->map(fn ($a) => [
                    'id' => $a->id,
                    'body' => $a->body,
                    'name' => $finished ? $a->user->name : null,
                    'votes' => $finished && $room->judging_mode === Room::MODE_ONLINE ? [
                        'funny' => $a->votes()->where('choice', Vote::FUNNY)->count(),
                        'meh' => $a->votes()->where('choice', Vote::MEH)->count(),
                    ] : null,
                ]),
                'results' => $finished ? $round->results()->with('user:id,name')->orderBy('rank')->get()->map(fn ($r) => [
                    'name' => $r->user->name, 'score' => $r->score, 'rank' => $r->rank, 'is_eliminated' => $r->is_eliminated,
                ]) : null,
            ];
        }

        return response()->json($payload);
    }

    // ---- host actions ----

    /** 大喜利掲示板からランダムに3件のお題候補を返す */
    public function odaiCandidates(Request $request, string $code, OdaiSource $source): JsonResponse
    {
        $this->hostRoom($code, $request);

        try {
            return response()->json(['candidates' => $source->candidates(3)]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => $e instanceof \RuntimeException ? $e->getMessage() : 'お題を取得できませんでした'], 502);
        }
    }

    public function startRound(Request $request, string $code, OdaiSource $source)
    {
        $room = $this->hostRoom($code, $request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'time_limit_sec' => ['required', 'integer', 'min:10', 'max:600'],
            'source_url' => ['nullable', 'string', 'max:500'],
        ]);
        abort_if(! empty($data['source_url']) && ! $source->isSourceUrl($data['source_url']), 422, '出典URLが不正です');

        $last = $room->rounds()->latest('round_number')->first();
        abort_if($last && $last->status !== 'finished', 422, '進行中のラウンドがあります');
        abort_if($room->status === 'finished', 422, '大会は終了しています');
        abort_if($this->activePerformers($room)->count() < 2, 422, '演者が2人以上必要です');

        $round = Round::create([
            'room_id' => $room->id,
            'odai_id' => Odai::create(['title' => $data['title'], 'source_url' => $data['source_url'] ?? null, 'used_at' => now()])->id,
            'round_number' => ($last->round_number ?? 0) + 1,
            'time_limit_sec' => $data['time_limit_sec'],
            'started_at' => now(),
        ]);
        $room->update(['status' => 'in_progress']);
        RoundStarted::dispatch($round);

        return response()->noContent();
    }

    /** 回答受付を締め切り、ランダムな公開順を決める */
    public function closeAnswers(Request $request, string $code)
    {
        $round = $this->currentRound($this->hostRoom($code, $request), 'collecting');
        $this->closeCollecting($round);

        return response()->noContent();
    }

    /**
     * 次の回答を1件公開する。公開順の次にあたる演者本人が行う(ホストは演者が操作できないときの代行)。
     */
    public function revealNext(Request $request, string $code)
    {
        $room = $this->room($code);
        $me = $request->user();
        $isHost = $room->host_id === $me->id;

        $next = DB::transaction(function () use ($room, $request, $me, $isHost) {
            $round = $this->currentRound($room, ['revealing', 'voting'], lock: true);
            if ($isHost) {
                $this->saveVolume($round, $request);
            }

            $next = $round->answers()->whereNull('revealed_at')->whereNotNull('reveal_order')->orderBy('reveal_order')->first();
            abort_unless($next, 422, '公開する回答がありません');
            abort_unless($isHost || $next->user_id === $me->id, 403, '次に公開されるのは別の演者の回答です');
            $next->update(['revealed_at' => now()]);

            return $next;
        });
        AnswerRevealed::dispatch($next);

        return response()->noContent();
    }

    /** offlineモード: ホストPCで測った、公開済みの回答1件分の音量合計を保存する */
    public function volume(Request $request, string $code)
    {
        $room = $this->hostRoom($code, $request);
        abort_unless($room->judging_mode === Room::MODE_OFFLINE, 422, 'このルームは笑い声モードではありません');
        $round = $this->currentRound($room, ['revealing', 'voting']);

        $data = $request->validate([
            'answer_id' => ['required', 'integer'],
            'volume_sum' => ['required', 'numeric', 'min:0'],
        ]);
        $answer = $round->answers()->whereNotNull('revealed_at')->findOrFail($data['answer_id']);
        $answer->update(['raw_volume_sum' => (float) $data['volume_sum']]);

        return response()->noContent();
    }

    public function finishRound(Request $request, string $code)
    {
        $room = $this->hostRoom($code, $request);

        $round = DB::transaction(function () use ($room, $request) {
            $round = $this->currentRound($room, ['revealing', 'voting'], lock: true);
            abort_if($round->answers()->whereNull('revealed_at')->whereNotNull('reveal_order')->exists(), 422, '未公開の回答があります');
            $this->saveVolume($round, $request);
            // 集計中に他の操作が入らないよう、確定までこのトランザクション内で行う
            app(RoundService::class)->finish($round);

            return $round;
        });

        $reachedLimit = $room->max_rounds && $round->round_number >= $room->max_rounds;
        if ($reachedLimit || $this->activePerformers($room)->count() <= 1) {
            $room->update(['status' => 'finished']);
        }

        return response()->noContent();
    }

    // ---- performer / judge actions ----

    public function answer(Request $request, string $code)
    {
        $room = $this->room($code);
        $me = $request->user();
        abort_unless($this->activePerformers($room)->contains('user_id', $me->id), 403, '回答できません');
        $round = $this->currentRound($room, 'collecting');
        abort_if(now()->gt($round->started_at->addSeconds($round->time_limit_sec + 2)), 422, '受付は終了しました');

        $data = $request->validate(['body' => ['required', 'string', 'max:200']]);
        $answer = Answer::updateOrCreate(
            ['round_id' => $round->id, 'user_id' => $me->id],
            ['body' => $data['body'], 'submitted_at' => now()],
        );
        AnswerSubmitted::dispatch($answer);

        return response()->noContent();
    }

    public function vote(Request $request, string $code)
    {
        $room = $this->room($code);
        abort_unless($room->judging_mode === Room::MODE_ONLINE, 422, 'このルームは投票モードではありません');
        abort_unless($this->roleOf($room, $request) === 'judge', 403, '審査員のみ投票できます');
        $round = $this->currentRound($room, 'voting');

        $data = $request->validate(['choice' => ['required', Rule::in([Vote::FUNNY, Vote::MEH])]]);
        $answer = $round->answers()->whereNotNull('revealed_at')->orderByDesc('reveal_order')->firstOrFail();

        $vote = Vote::updateOrCreate(
            ['answer_id' => $answer->id, 'voter_id' => $request->user()->id],
            ['choice' => $data['choice']],
        );
        VoteCast::dispatch($vote);

        return response()->noContent();
    }

    // ---- helpers ----

    private function room(string $code): Room
    {
        return Room::where('code', $code)->firstOrFail();
    }

    private function hostRoom(string $code, Request $request): Room
    {
        $room = $this->room($code);
        abort_unless($room->host_id === $request->user()->id, 403);

        return $room;
    }

    /** ホスト以外は入室時に演者(定員まで)/審査員として登録する */
    private function ensureMember(Room $room, Request $request): void
    {
        $me = $request->user();
        if ($room->host_id === $me->id || $room->members()->where('user_id', $me->id)->exists()) {
            return;
        }

        $role = $room->members()->where('role', 'performer')->count() < $room->max_performers ? 'performer' : 'judge';
        RoomMember::create(['room_id' => $room->id, 'user_id' => $me->id, 'role' => $role]);
    }

    private function roleOf(Room $room, Request $request): string
    {
        return $room->host_id === $request->user()->id
            ? 'host'
            : $room->members()->where('user_id', $request->user()->id)->value('role');
    }

    private function activePerformers(Room $room)
    {
        return $room->members()->where('role', 'performer')->where('is_eliminated', false)->get();
    }

    private function currentRound(Room $room, string|array $status, bool $lock = false): Round
    {
        $query = $room->rounds()->latest('round_number');
        $round = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($round && in_array($round->status, (array) $status, true), 422, '現在は実行できない操作です');

        return $round;
    }

    /** 制限時間(+猶予)を過ぎたら回答受付を自動で締め切る。stateの取得時に遅延実行する */
    private function autoCloseIfExpired(Round $round): bool
    {
        if ($round->status !== 'collecting' || now()->lt($round->started_at->addSeconds($round->time_limit_sec + 2))) {
            return false;
        }

        return $this->closeCollecting($round);
    }

    /** collecting → revealing/voting。二重実行されても一度だけ公開順を決める */
    private function closeCollecting(Round $round): bool
    {
        $closed = DB::transaction(function () use ($round) {
            $locked = Round::whereKey($round->id)->lockForUpdate()->first();
            if ($locked->status !== 'collecting') {
                return false;
            }
            $locked->answers()->get()->shuffle()->values()->each(
                fn (Answer $a, int $i) => $a->update(['reveal_order' => $i + 1])
            );
            $locked->update(['status' => $this->revealStatus($locked)]);

            return true;
        });
        if ($closed) {
            $this->broadcastRefresh($round->refresh());
        }

        return $closed;
    }

    private function revealStatus(Round $round): string
    {
        return $round->room->judging_mode === Room::MODE_ONLINE ? 'voting' : 'revealing';
    }

    private function saveVolume(Round $round, Request $request): void
    {
        if ($round->room->judging_mode !== Room::MODE_OFFLINE || ! $request->has('volume_sum')) {
            return;
        }
        $current = $round->answers()->whereNotNull('revealed_at')->orderByDesc('reveal_order')->first();
        $current?->update(['raw_volume_sum' => (float) $request->input('volume_sum')]);
    }

    /** 状態変化を全員に知らせる(フロントは受信後にstateを再取得する) */
    private function broadcastRefresh(Round $round): void
    {
        RoundUpdated::dispatch($round);
    }
}
