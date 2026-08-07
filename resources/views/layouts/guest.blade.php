<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="dark">

        <title>{{ config('app.name', 'Glory SMM') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-zinc-100">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6">
            <a href="/" class="mb-8 flex flex-col items-center gap-3">
                <x-application-logo variant="full" class="h-24 w-24 rounded-2xl shadow-soft ring-1 ring-white/10" />
                <div class="text-center">
                    <div class="text-lg font-semibold tracking-tight text-white">Glory SMM</div>
                    <div class="text-xs tracking-wide text-zinc-500">Social publishing</div>
                </div>
            </a>

            <div class="w-full max-w-md rounded-2xl border border-surface-border bg-surface p-6 shadow-soft sm:p-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
