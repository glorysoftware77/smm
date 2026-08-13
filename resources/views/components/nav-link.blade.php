@props(['active'])

@php
$classes = ($active ?? false)
            ? 'rounded-lg px-3.5 py-2 text-[15px] font-semibold text-white bg-glory-500'
            : 'rounded-lg px-3.5 py-2 text-[15px] font-semibold text-[#1A1D23] transition hover:bg-[#F5F6F8]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
