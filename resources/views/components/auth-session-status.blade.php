@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm font-bold text-moegi-dark']) }}>
        {{ $status }}
    </div>
@endif
