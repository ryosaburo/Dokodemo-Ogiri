@extends('layouts.game')

@section('content')
<section class="mb-8">
    <div class="mekuri px-6 py-8 sm:px-10">
        <h1 class="text-4xl leading-tight sm:text-5xl">お題に一言、<br>いちばん笑わせた人が残る。</h1>
    </div>
</section>

@guest
    <section class="stage-panel p-6">
        <p class="mb-4 text-stone-300">ルームの作成・参加にはアカウントが必要です。</p>
        <div class="flex gap-3">
            <a href="{{ route('register') }}" class="btn">新規登録</a>
            <a href="{{ route('login') }}" class="btn btn-quiet">ログイン</a>
        </div>
    </section>
@else
    <div class="grid gap-6 md:grid-cols-2">
        <section class="stage-panel p-6">
            <h2 class="mb-4 text-lg font-bold">ルームを作る</h2>
            <form method="POST" action="{{ route('rooms.store') }}" class="space-y-4">
                @csrf
                @include('partials.dev-as')
                <label class="block text-sm">審査モード
                    <select name="judging_mode" class="field mt-1">
                        <option value="online_vote">オンライン(面白い/微妙 で投票)</option>
                        <option value="offline_laugh">オフライン(会場の笑い声の大きさ)</option>
                    </select>
                </label>
                <label class="block text-sm">演者の定員(2〜8人)
                    <input type="number" name="max_performers" value="4" min="2" max="8" class="field mt-1">
                </label>
                <label class="block text-sm">規定ラウンド数(空欄なら演者が1人になるまで)
                    <input type="number" name="max_rounds" min="1" max="50" placeholder="無制限" class="field mt-1">
                </label>
                <button class="btn w-full">ルームを作成</button>
            </form>
        </section>
        <section class="stage-panel p-6">
            <h2 class="mb-4 text-lg font-bold">ルームに入る</h2>
            <form method="POST" action="{{ route('rooms.join') }}" class="space-y-4">
                @csrf
                @include('partials.dev-as')
                <label class="block text-sm">招待コード
                    <input name="code" required maxlength="12" autocomplete="off"
                           class="field mt-1 text-center text-2xl font-bold uppercase tracking-[0.3em]">
                </label>
                @error('code')<p class="text-sm text-red-400">{{ $message }}</p>@enderror
                <button class="btn btn-quiet w-full">入室</button>
            </form>
            <p class="mt-4 text-sm text-stone-400">演者の定員に達したあとに入った人は、審査員になります。</p>
        </section>
    </div>
@endguest

@if (app()->environment('local'))
    @include('partials.dev-panel')
@endif
@endsection
