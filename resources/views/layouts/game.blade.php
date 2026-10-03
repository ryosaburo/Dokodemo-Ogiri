<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'どこでも大喜利')</title>
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
<header class="bg-sumi text-white">
    <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
        <a href="{{ route('home') }}" class="font-logo text-2xl leading-none tracking-wider">どこでも大喜利</a>
        @auth
            <div class="flex items-center gap-3 text-sm">
                @if (app()->environment('local') && request('as'))
                    <span class="bg-kaki px-2 py-0.5 text-xs font-bold text-sumi">開発: {{ auth()->user()->name }}</span>
                @endif
                <a href="{{ route('profile.edit') }}" class="hover:underline">{{ auth()->user()->name }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-quiet !px-3 !py-1 text-sm">ログアウト</button>
                </form>
            </div>
        @else
            <nav class="flex items-center gap-3 text-sm">
                <a href="{{ route('login') }}" class="hover:underline">ログイン</a>
                <a href="{{ route('register') }}" class="btn !px-3 !py-1 text-sm">新規登録</a>
            </nav>
        @endauth
    </div>
    <div class="curtain"></div>
</header>
<main class="mx-auto max-w-4xl px-4 py-6">
    @yield('content')
</main>
</body>
</html>
