<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-quiet !text-sumi !border-sumi/50']) }}>
    {{ $slot }}
</button>
