@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-[#1A1D23]']) }}>
    {{ $value ?? $slot }}
</label>
