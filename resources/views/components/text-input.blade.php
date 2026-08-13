@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-raised border-surface-border text-slate-100 placeholder:text-slate-400 focus:border-glory-400 focus:ring-glory-400 rounded-lg shadow-sm']) }}>
