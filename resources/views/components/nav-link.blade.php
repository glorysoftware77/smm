@props(['active'])

@php
$classes = ($active ?? false)
            ? 'rounded-lg px-3 py-2 text-sm font-medium text-white bg-white/[0.06] ring-1 ring-white/10'
            : 'rounded-lg px-3 py-2 text-sm font-medium text-zinc-400 transition hover:bg-white/[0.03] hover:text-zinc-100';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
