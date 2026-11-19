@props(['variant' => 'mark'])

@php
    $src = $variant === 'full'
        ? asset('images/glory-logo.png')
        : asset('images/glory-mark.png');
    $alt = 'Glory Software Technologies';
@endphp

<img
    src="{{ $src }}"
    alt="{{ $alt }}"
    {{ $attributes->merge(['class' => 'object-cover']) }}
>
