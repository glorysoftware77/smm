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
            'accent' => 'bg-zinc-900 ring-1 ring-white/20',
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
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-glory-400">Workspace</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-white">Connected accounts</h2>
                <p class="mt-1 max-w-xl text-sm text-zinc-400">
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
                <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="panel px-4 py-4">
                    <div class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">Networks</div>
                    <div class="mt-1 text-2xl font-semibold text-white">{{ $connectedCount }}<span class="text-base font-normal text-zinc-500"> / 4</span></div>
                </div>
                <div class="panel px-4 py-4">
                    <div class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">Profiles</div>
                    <div class="mt-1 text-2xl font-semibold text-white">{{ $accountCount }}</div>
                </div>
                <div class="panel col-span-2 px-4 py-4">
                    <div class="text-[11px] font-medium uppercase tracking-wider text-zinc-500">Status</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($platforms as $p)
                            <span class="inline-flex items-center gap-2 rounded-full border border-surface-border bg-surface-raised px-2.5 py-1 text-xs text-zinc-300">
                                <span class="status-dot {{ $p['connected'] ? 'bg-emerald-400' : 'bg-zinc-600' }}"></span>
                                {{ $p['label'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($platforms as $p)
                    <section class="panel">
                        <div class="flex items-start justify-between gap-4 border-b border-surface-border px-5 py-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $p['accent'] }} text-sm font-bold text-white shadow-soft">
                                    @if ($p['key'] === 'facebook') f
                                    @elseif ($p['key'] === 'instagram') Ig
                                    @elseif ($p['key'] === 'youtube') ▶
                                    @else TT
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-semibold text-white">{{ $p['label'] }}</h3>
                                        @if ($p['connected'])
                                            <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-300">Live</span>
                                        @else
                                            <span class="rounded-full bg-zinc-500/15 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-zinc-400">Offline</span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-sm text-zinc-500">{{ $p['hint'] }}</p>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap justify-end gap-2">
                                <a href="{{ $p['connect'] }}" class="btn-primary text-xs">{{ $p['connectLabel'] }}</a>

                                @if ($p['showRefresh'])
                                    <form method="POST" action="{{ $p['refresh'] }}">
                                        @csrf
                                        <button type="submit" class="btn-secondary text-xs">Refresh</button>
                                    </form>
                                @endif

                                @if ($p['connected'])
                                    <form method="POST" action="{{ $p['disconnect'] }}"
                                          onsubmit="return confirm(@js($p['disconnectConfirm']));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-secondary text-xs">Disconnect</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="px-5 py-3">
                            @if ($p['accounts']->isEmpty())
                                <p class="py-3 text-sm text-zinc-500">{{ $p['empty'] }}</p>
                            @else
                                <ul class="divide-y divide-surface-border">
                                    @foreach ($p['accounts'] as $account)
                                        <li class="flex items-center justify-between gap-3 py-3">
                                            <div class="flex min-w-0 items-center gap-3">
                                                @if ($account->picture_url)
                                                    <img src="{{ $account->picture_url }}" alt="" class="h-10 w-10 rounded-full object-cover ring-1 ring-white/10">
                                                @else
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-surface-muted text-sm font-medium text-zinc-300 ring-1 ring-white/5">
                                                        {{ strtoupper(substr($account->name, 0, 1)) }}
                                                    </div>
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="truncate font-medium text-zinc-100">{{ $account->name }}</div>
                                                    <div class="truncate text-xs text-zinc-500">
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
