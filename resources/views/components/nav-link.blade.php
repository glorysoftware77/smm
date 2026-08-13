@props(['active'])

@php
$classes = ($active ?? false)
            ? 'text-[15px] font-semibold text-glory-500 underline decoration-2 underline-offset-[18px]'
            : 'text-[15px] font-semibold text-[#5C534C] transition hover:text-[#1A1D23]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
