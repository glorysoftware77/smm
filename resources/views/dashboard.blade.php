@php
    $platforms = [
        [
            'key' => 'facebook',
            'label' => 'Facebook',
            'hint' => 'Pages you manage',
            'connected' => $hasFacebookAccount,
            'accounts' => $pages,
            'count' => $pages->count(),
            'accent' => 'bg-[#1877F2]',
            'accentSoft' => 'from-[#1877F2]/12 to-transparent',
            'connect' => route('facebook.redirect'),
            'connectLabel' => $hasFacebookAccount ? 'Reconnect' : 'Connect',
            'showRefresh' => $hasFacebookAccount,
            'refresh' => route('facebook.sync'),
            'disconnect' => route('facebook.disconnect'),
            'disconnectConfirm' => 'Disconnect Facebook and all linked pages?',
            'canRemove' => true,
            'empty' => 'No Facebook Pages connected yet.',
        ],
        [
            'key' => 'instagram',
            'label' => 'Instagram',
            'hint' => 'Business Login',
            'connected' => $hasInstagramAccount,
            'accounts' => $instagramAccounts,
            'count' => $instagramAccounts->count(),
            'accent' => 'bg-gradient-to-br from-[#F58529] via-[#DD2A7B] to-[#8134AF]',
            'accentSoft' => 'from-[#DD2A7B]/12 to-transparent',
            'connect' => route('instagram.redirect'),
            'connectLabel' => $hasInstagramAccount ? 'Reconnect' : 'Connect',
            'showRefresh' => false,
            'refresh' => null,
            'disconnect' => route('instagram.disconnect'),
            'disconnectConfirm' => 'Disconnect Instagram?',
            'canRemove' => true,
            'empty' => 'No Instagram accounts linked yet.',
        ],
        [
            'key' => 'youtube',
            'label' => 'YouTube',
            'hint' => 'Videos & Shorts',
            'connected' => $hasYouTubeAccount,
            'accounts' => $youtubeChannels,
            'count' => $youtubeChannels->count(),
            'accent' => 'bg-[#FF0000]',
            'accentSoft' => 'from-[#FF0000]/12 to-transparent',
            'connect' => route('youtube.redirect'),
            'connectLabel' => $hasYouTubeAccount ? 'Reconnect' : 'Connect',
            'showRefresh' => false,
            'refresh' => null,
            'disconnect' => route('youtube.disconnect'),
            'disconnectConfirm' => 'Disconnect YouTube?',
            'canRemove' => false,
            'empty' => 'No YouTube channels linked yet.',
        ],
        [
            'key' => 'tiktok',
            'label' => 'TikTok',
            'hint' => 'Direct Post videos',
            'connected' => $hasTikTokAccount,
            'accounts' => $tiktokAccounts,
            'count' => $tiktokAccounts->count(),
            'accent' => 'bg-[#1A1D23]',
            'accentSoft' => 'from-[#1A1D23]/10 to-transparent',
            'connect' => route('tiktok.redirect'),
            'connectLabel' => $hasTikTokAccount ? 'Reconnect' : 'Connect',
            'showRefresh' => false,
            'refresh' => null,
            'disconnect' => route('tiktok.disconnect'),
            'disconnectConfirm' => 'Disconnect TikTok?',
            'canRemove' => false,
            'empty' => 'No TikTok accounts linked yet.',
        ],
    ];

    $connectedCount = collect($platforms)->where('connected', true)->count();
    $accountCount = collect($platforms)->sum('count');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="kicker">Workspace</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[#1A1D23] sm:text-4xl">Connected accounts</h2>
                <p class="mt-2 max-w-xl text-[15px] leading-relaxed text-[#5C534C]">
                    Link the networks you publish to. Connected profiles show up in Create Post and Insights.
                </p>
            </div>
            <a href="{{ route('posts.create') }}" class="btn-primary shrink-0">
                Create post
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-xl border border-red-200/80 bg-red-50/90 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="panel px-5 py-5">
                    <div class="kicker">Networks</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-[#1A1D23]">{{ $connectedCount }}<span class="text-lg font-normal text-[#8B8680]"> / 4</span></div>
                </div>
                <div class="panel px-5 py-5">
                    <div class="kicker">Profiles</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-[#1A1D23]">{{ $accountCount }}</div>
                </div>
                <div class="panel col-span-2 px-5 py-5">
                    <div class="kicker">Status</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($platforms as $p)
                            <span class="inline-flex items-center gap-2 rounded-full border border-[#C9B8AD] bg-[#FFFCF9] px-3 py-1.5 text-xs font-semibold text-[#5C534C]">
                                <span class="status-dot {{ $p['connected'] ? 'bg-emerald-500' : 'bg-[#C4B8B0]' }}"></span>
                                {{ $p['label'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                @foreach ($platforms as $p)
                    <section class="panel relative overflow-hidden">
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-b {{ $p['accentSoft'] }}"></div>
                        <div class="relative flex items-start justify-between gap-4 border-b border-[#D4C3B8] px-6 py-5">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="mt-0.5 flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $p['accent'] }} text-sm font-bold text-white shadow-soft ring-4 ring-white/70">
                                    @if ($p['key'] === 'facebook') f
                                    @elseif ($p['key'] === 'instagram') Ig
                                    @elseif ($p['key'] === 'youtube') ▶
                                    @else TT
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold tracking-tight text-[#1A1D23]">{{ $p['label'] }}</h3>
                                        @if ($p['connected'])
                                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-100">Live</span>
                                        @else
                                            <span class="rounded-full bg-[#F0EBE6] px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#6B635C] ring-1 ring-[#E4D9D1]">Offline</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-[#5C534C]">{{ $p['hint'] }}</p>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap justify-end gap-2">
                                <a href="{{ $p['connect'] }}" class="{{ $p['connected'] ? 'btn-secondary' : 'btn-primary' }}">{{ $p['connectLabel'] }}</a>

                                @if ($p['showRefresh'])
                                    <form method="POST" action="{{ $p['refresh'] }}">
                                        @csrf
                                        <button type="submit" class="btn-ghost">Refresh</button>
                                    </form>
                                @endif

                                @if ($p['connected'])
                                    <form method="POST" action="{{ $p['disconnect'] }}"
                                          onsubmit="return confirm(@js($p['disconnectConfirm']));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-ghost">Disconnect</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="relative px-6 py-4">
                            @if ($p['accounts']->isEmpty())
                                <div class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-[#C9B8AD] bg-[#EFE5DC] px-4 py-6">
                                    <p class="text-sm text-[#5C534C]">{{ $p['empty'] }}</p>
                                    <a href="{{ $p['connect'] }}" class="btn-primary">{{ $p['connectLabel'] }}</a>
                                </div>
                            @else
                                <ul class="divide-y divide-[#D4C3B8]">
                                    @foreach ($p['accounts'] as $account)
                                        <li class="flex items-center justify-between gap-3 py-3.5">
                                            <div class="flex min-w-0 items-center gap-3">
                                                @if ($account->picture_url)
                                                    <img src="{{ $account->picture_url }}" alt="" class="h-10 w-10 rounded-full object-cover ring-2 ring-[#F7EFE8] shadow-sm">
                                                @else
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-glory-100 text-sm font-semibold text-glory-700 ring-2 ring-[#F7EFE8] shadow-sm">
                                                        {{ strtoupper(substr($account->name, 0, 1)) }}
                                                    </div>
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="truncate font-semibold text-[#1A1D23]">{{ $account->name }}</div>
                                                    <div class="truncate text-xs text-[#6F655C]">
                                                        @if ($p['key'] === 'facebook')
                                                            {{ $account->category ?: 'Facebook Page' }}
                                                        @else
                                                            {{ $p['label'] }}
                                                        @endif
                                                        · {{ Str::limit($account->page_id, 18) }}
                                                    </div>
                                                </div>
                                            </div>

                                            @if ($p['canRemove'])
                                                <form method="POST" action="{{ route('facebook.pages.disconnect', $account) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-danger-ghost">Remove</button>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
