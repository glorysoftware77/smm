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
        <div class="min-h-screen">
            @include('layouts.navigation')

            @isset($header)
                <header class="border-b border-surface-border bg-surface/80 backdrop-blur-md">
                    <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
