@extends('layouts.game')

@section('content')
@guest
<section class="mb-12">
    <div class="mekuri px-6 py-8 sm:px-10">
        <h1 class="text-4xl leading-tight sm:text-5xl">大喜利サバイバル開幕</h1>
    </div>
</section>
    <section class="stage-panel">
        <p class="mb-4">ルームを作るにも入るにも、アカウントが必要です。</p>
        <div class="flex gap-3">
            <a href="{{ route('register') }}" class="btn">新規登録</a>
            <a href="{{ route('login') }}" class="btn btn-quiet">ログイン</a>
        </div>
    </section>
@else
<div x-data="{ role: {{ $errors->has('code') ? "'performer'" : 'null' }} }">
    <section class="mb-8">
        <button type="button" @click="role = null" class="mekuri block w-full px-6 py-8 text-left sm:px-10"
                :aria-expanded="role === null ? 'false' : 'true'">
            <span class="block text-4xl leading-tight sm:text-5xl">大喜利サバイバル開幕</span>
        </button>
    </section>

    <section x-show="role === null" class="grid gap-4 sm:grid-cols-2">
        <button type="button" @click="role = 'host'" class="btn btn-quiet !block !py-6 !text-left">
            <span class="block font-brush text-2xl">司会者として始める</span>
            <span class="hint block font-normal">ルームを作って、お題を出し、進行します。</span>
        </button>
        <button type="button" @click="role = 'performer'" class="btn btn-quiet !block !py-6 !text-left">
            <span class="block font-brush text-2xl">演者として参加する</span>
            <span class="hint block font-normal">招待コードで入室して、回答します。</span>
        </button>
    </section>

    <section x-show="role === 'host'" x-cloak class="max-w-md">
        <h2 class="mb-1 font-brush text-2xl">ルームを作る</h2>
        <p class="hint mb-5">作った人が司会者になります。</p>
        <form method="POST" action="{{ route('rooms.store') }}" class="space-y-4">
            @csrf
            @include('partials.dev-as')
            <label class="block text-sm font-bold">審査の方法
                <select name="judging_mode" class="field mt-1">
                    <option value="online_vote">投票(面白い / 微妙)</option>
                    <option value="offline_laugh">会場の笑い声の大きさ</option>
                </select>
                <span class="hint block font-normal">笑い声は、司会者のマイクで測ります。</span>
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
            <button class="btn w-full">ルームを作る</button>
        </form>
        <button type="button" @click="role = null" class="hint mt-4 underline">役割を選び直す</button>
    </section>

    <section x-show="role === 'performer'" x-cloak class="max-w-md">
        <h2 class="mb-1 font-brush text-2xl">ルームに入る</h2>
        <p class="hint mb-5">司会者から受け取った招待コードを入れてください。</p>
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
        <button type="button" @click="role = null" class="hint mt-4 underline">役割を選び直す</button>
    </section>
</div>
@endguest


@if (app()->environment('local'))
    @include('partials.dev-panel')
@endif
@endsection
