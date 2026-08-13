<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light">

        <title>{{ config('app.name', 'Glory SMM') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-[#1A1D23] bg-[#F5F6F8]">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6">
            <a href="/" class="mb-8 flex flex-col items-center gap-3">
                <x-application-logo variant="full" class="h-24 w-24 rounded-2xl shadow-soft ring-1 ring-[#E4E7EC]" />
                <div class="text-center">
                    <div class="text-lg font-semibold tracking-tight text-[#1A1D23]">Glory SMM</div>
                    <div class="text-xs font-medium tracking-wide text-[#5C6570]">Social publishing</div>
                </div>
            </a>

            <div class="w-full max-w-md rounded-2xl border border-[#E4E7EC] bg-white p-6 shadow-soft sm:p-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
