<details class="mt-16 border-t border-dashed border-kaki/50 pt-4 text-sm"><summary class="cursor-pointer font-bold text-kaki">開発用: ユーザー切り替え</summary><section class="mt-4">
        <p class="mb-3 text-xs text-stone-400">
        URLに <code>?as=ID</code> を付けて開くと、そのタブだけ指定ユーザーとして動きます(localのみ)。
    </p>
    <form method="POST" action="{{ route('dev.users') }}" class="mb-4 flex gap-2">
        @csrf
        @include('partials.dev-as')
        <input name="names" required placeholder="名前を空白かカンマで区切って入力(例: ホスト 演者A 演者B 審査員)" class="field flex-1 text-sm">
        <button class="btn btn-quiet !py-2 text-sm">作成</button>
    </form>
    <ul class="grid gap-2 sm:grid-cols-2">
        @forelse ($devUsers as $u)
            <li class="flex items-center justify-between bg-stage-2 px-3 py-2 text-sm {{ request('as') == $u->id ? 'ring-2 ring-kaki' : '' }}">
                <span>#{{ $u->id }} {{ $u->name }}</span>
                <a href="{{ route('home', ['as' => $u->id]) }}" target="_blank" class="text-kaki underline">このユーザーで開く</a>
            </li>
        @empty
            <li class="text-sm text-stone-400">ユーザーがいません。上のフォームで作成してください。</li>
        @endforelse
    </ul>
</section></details>
