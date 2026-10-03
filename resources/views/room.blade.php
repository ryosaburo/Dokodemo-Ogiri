@extends('layouts.game')

@section('title', 'ルーム '.$room->code)

@section('content')
<div id="room-app" data-code="{{ $room->code }}" data-room-id="{{ $room->id }}" data-user-id="{{ auth()->id() }}">
    <p class="text-stone-500">読み込み中…</p>
</div>
@endsection
