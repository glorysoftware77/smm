@props(['active'])

@php
$classes = ($active ?? false)
            ? 'rounded-lg px-3.5 py-2 text-[15px] font-semibold text-white bg-white/10'
            : 'rounded-lg px-3.5 py-2 text-[15px] font-semibold text-slate-200 transition hover:bg-white/5 hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
