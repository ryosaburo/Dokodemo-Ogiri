@extends('layouts.game')

@section('content')
<section class="mb-12">
    <div class="mekuri px-6 py-8 sm:px-10">
        <h1 class="text-4xl leading-tight sm:text-5xl">大喜利サバイバル開幕</h1>
    </div>
</section>

@guest
    <section class="stage-panel">
        <p class="mb-4">ルームを作るにも入るにも、アカウントが必要です。</p>
        <div class="flex gap-3">
            <a href="{{ route('register') }}" class="btn">新規登録</a>
            <a href="{{ route('login') }}" class="btn btn-quiet">ログイン</a>
        </div>
    </section>
@else
    <div class="grid gap-10 md:grid-cols-[1fr_1px_1fr] md:gap-8">
        <section>
            <h2 class="mb-1 font-brush text-2xl">ルームに入る</h2>
            <p class="hint mb-5">ホストから受け取った招待コードを入れてください。</p>
            <form method="POST" action="{{ route('rooms.join') }}" class="space-y-4">
                @csrf
                @include('partials.dev-as')
                <label class="block text-sm font-bold">招待コード
                    <input name="code" required maxlength="12" autocomplete="off"
                           class="field mt-1 text-center text-2xl font-bold uppercase tracking-[0.3em]">
                </label>
                @error('code')<p class="text-sm font-bold text-kaki">{{ $message }}</p>@enderror
                <button class="btn w-full">入室する</button>
            </form>
            <p class="hint mt-4">演者の定員が埋まったあとは、審査員として入ります。</p>
        </section>

        <div class="hidden bg-paper/20 md:block" aria-hidden="true"></div>

        <section>
            <h2 class="mb-1 font-brush text-2xl">ルームを作る</h2>
            <p class="hint mb-5">作った人がホストになり、お題を出して進行します。</p>
            <form method="POST" action="{{ route('rooms.store') }}" class="space-y-4">
                @csrf
                @include('partials.dev-as')
                <label class="block text-sm font-bold">審査の方法
                    <select name="judging_mode" class="field mt-1">
                        <option value="online_vote">投票(面白い / 微妙)</option>
                        <option value="offline_laugh">会場の笑い声の大きさ</option>
                    </select>
                    <span class="hint block font-normal">笑い声は、ホストのマイクで測ります。</span>
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <label class="block text-sm font-bold">演者の人数
                        <input type="number" name="max_performers" value="4" min="2" max="8" class="field mt-1">
                        <span class="hint block font-normal">2〜8人</span>
                    </label>
                    <label class="block text-sm font-bold">ラウンド数
                        <input type="number" name="max_rounds" min="1" max="50" placeholder="決めない" class="field mt-1">
                        <span class="hint block font-normal">空欄なら、1人になるまで</span>
                    </label>
                </div>
                <button class="btn btn-quiet w-full">ルームを作る</button>
            </form>
        </section>
    </div>
@endguest

@if (app()->environment('local'))
    @include('partials.dev-panel')
@endif
@endsection
