@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-zinc-900 border-zinc-600 text-zinc-100 placeholder:text-zinc-500 focus:border-indigo-400 focus:ring-indigo-400 rounded-md shadow-sm']) }}>
