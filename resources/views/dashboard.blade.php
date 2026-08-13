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
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="kicker">Workspace</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[#1A1D23]">Connected accounts</h2>
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
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
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
                            <span class="inline-flex items-center gap-2 rounded-full border border-[#E8E1DB] bg-[#FAF8F6] px-3 py-1.5 text-xs font-semibold text-[#5C534C]">
                                <span class="status-dot {{ $p['connected'] ? 'bg-emerald-500' : 'bg-[#C4B8B0]' }}"></span>
                                {{ $p['label'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                @foreach ($platforms as $p)
                    <section class="panel relative">
                        <div class="absolute inset-y-0 left-0 w-1 {{ $p['accent'] }}"></div>
                        <div class="flex items-start justify-between gap-4 border-b border-[#E8E1DB] px-6 py-5">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $p['accent'] }} text-sm font-bold text-white shadow-soft">
                                    @if ($p['key'] === 'facebook') f
                                    @elseif ($p['key'] === 'instagram') Ig
                                    @elseif ($p['key'] === 'youtube') ▶
                                    @else TT
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-semibold text-[#1A1D23]">{{ $p['label'] }}</h3>
                                        @if ($p['connected'])
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-800">Live</span>
                                        @else
                                            <span class="rounded-full bg-[#EEF0F3] px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-[#5C6570]">Offline</span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-sm text-[#5C6570]">{{ $p['hint'] }}</p>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap justify-end gap-2">
                                <a href="{{ $p['connect'] }}" class="btn-primary">{{ $p['connectLabel'] }}</a>

                                @if ($p['showRefresh'])
                                    <form method="POST" action="{{ $p['refresh'] }}">
                                        @csrf
                                        <button type="submit" class="btn-secondary">Refresh</button>
                                    </form>
                                @endif

                                @if ($p['connected'])
                                    <form method="POST" action="{{ $p['disconnect'] }}"
                                          onsubmit="return confirm(@js($p['disconnectConfirm']));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-secondary">Disconnect</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="px-6 py-4">
                            @if ($p['accounts']->isEmpty())
                                <p class="py-3 text-sm text-[#5C6570]">{{ $p['empty'] }}</p>
                            @else
                                <ul class="divide-y divide-[#E4E7EC]">
                                    @foreach ($p['accounts'] as $account)
                                        <li class="flex items-center justify-between gap-3 py-3">
                                            <div class="flex min-w-0 items-center gap-3">
                                                @if ($account->picture_url)
                                                    <img src="{{ $account->picture_url }}" alt="" class="h-10 w-10 rounded-full object-cover ring-1 ring-[#E4E7EC]">
                                                @else
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#EEF0F3] text-sm font-medium text-[#5C6570] ring-1 ring-[#E4E7EC]">
                                                        {{ strtoupper(substr($account->name, 0, 1)) }}
                                                    </div>
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="truncate font-medium text-[#1A1D23]">{{ $account->name }}</div>
                                                    <div class="truncate text-xs text-[#5C6570]">
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
