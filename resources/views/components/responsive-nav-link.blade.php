@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-lg px-3 py-2 text-start text-base font-medium text-glory-200 bg-glory-950/50 ring-1 ring-glory-500/20'
            : 'block w-full rounded-lg px-3 py-2 text-start text-base font-medium text-zinc-400 transition hover:bg-white/[0.03] hover:text-zinc-100';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
