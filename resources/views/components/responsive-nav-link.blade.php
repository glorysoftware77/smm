@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-lg px-3 py-2.5 text-start text-base font-semibold text-white bg-white/10'
            : 'block w-full rounded-lg px-3 py-2.5 text-start text-base font-semibold text-slate-200 transition hover:bg-white/5 hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
