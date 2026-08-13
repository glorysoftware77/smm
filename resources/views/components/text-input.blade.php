@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-white border-[#E4E7EC] text-[#1A1D23] placeholder:text-[#8B939E] focus:border-glory-500 focus:ring-glory-500 rounded-lg shadow-sm']) }}>
