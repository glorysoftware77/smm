@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-lg px-3 py-2.5 text-start text-base font-semibold text-white bg-glory-500'
            : 'block w-full rounded-lg px-3 py-2.5 text-start text-base font-semibold text-[#1A1D23] transition hover:bg-[#F5F6F8]';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
