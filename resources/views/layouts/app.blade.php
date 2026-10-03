@extends('layouts.game')

@section('content')
    @isset($header)
        <h1 class="mb-6 font-brush text-3xl">{{ $header }}</h1>
    @endisset
    {{ $slot }}
@endsection
