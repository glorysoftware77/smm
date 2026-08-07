<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-md bg-green-950/40 p-4 text-sm text-green-300">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-950/40 p-4 text-sm text-red-300">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-zinc-900 border border-zinc-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-zinc-100 space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium">Facebook Pages</h3>
                            <p class="text-sm text-zinc-300 mt-1">
                                Connect your Facebook account to link Pages you manage.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('facebook.redirect') }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition">
                                {{ $hasFacebookAccount ? 'Reconnect Facebook' : 'Connect Facebook' }}
                            </a>

                            @if ($hasFacebookAccount)
                                <form method="POST" action="{{ route('facebook.sync') }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-zinc-900 border border-zinc-600 rounded-md font-semibold text-xs text-zinc-200 uppercase tracking-widest hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition">
                                        Refresh Pages
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('facebook.disconnect') }}"
                                      onsubmit="return confirm('Disconnect Facebook and all linked pages?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-zinc-900 border border-zinc-600 rounded-md font-semibold text-xs text-zinc-200 uppercase tracking-widest hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition">
                                        Disconnect
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($pages->isEmpty())
                        <p class="text-sm text-zinc-400 border-t border-zinc-800 pt-4">
                            No Facebook Pages connected yet.
                        </p>
                    @else
                        <ul class="divide-y divide-zinc-800 border-t border-zinc-800">
                            @foreach ($pages as $page)
                                <li class="py-4 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if ($page->picture_url)
                                            <img src="{{ $page->picture_url }}" alt="" class="h-10 w-10 rounded-full object-cover">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-zinc-700 flex items-center justify-center text-sm font-medium text-zinc-300">
                                                {{ strtoupper(substr($page->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-medium text-zinc-100 truncate">{{ $page->name }}</div>
                                            <div class="text-sm text-zinc-400 truncate">
                                                {{ $page->category ?: 'Facebook Page' }} · ID {{ $page->page_id }}
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('facebook.pages.disconnect', $page) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-400 hover:text-red-200">
                                            Remove
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="bg-zinc-900 border border-zinc-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-zinc-100 space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium">Instagram Accounts</h3>
                            <p class="text-sm text-zinc-300 mt-1">
                                Connect with Instagram Business Login (separate from Facebook).
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('instagram.redirect') }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition">
                                {{ $hasInstagramAccount ? 'Reconnect Instagram' : 'Connect Instagram' }}
                            </a>

                            @if ($hasInstagramAccount)
                                <form method="POST" action="{{ route('instagram.disconnect') }}"
                                      onsubmit="return confirm('Disconnect Instagram?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-zinc-900 border border-zinc-600 rounded-md font-semibold text-xs text-zinc-200 uppercase tracking-widest hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition">
                                        Disconnect
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($instagramAccounts->isEmpty())
                        <p class="text-sm text-zinc-400 border-t border-zinc-800 pt-4">
                            No Instagram accounts linked yet.
                        </p>
                    @else
                        <ul class="divide-y divide-zinc-800 border-t border-zinc-800">
                            @foreach ($instagramAccounts as $account)
                                <li class="py-4 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if ($account->picture_url)
                                            <img src="{{ $account->picture_url }}" alt="" class="h-10 w-10 rounded-full object-cover">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-zinc-700 flex items-center justify-center text-sm font-medium text-zinc-300">
                                                IG
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-medium text-zinc-100 truncate">{{ $account->name }}</div>
                                            <div class="text-sm text-zinc-400 truncate">
                                                Instagram · ID {{ $account->page_id }}
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('facebook.pages.disconnect', $account) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-400 hover:text-red-200">
                                            Remove
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="bg-zinc-900 border border-zinc-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-zinc-100 space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium">YouTube Channels</h3>
                            <p class="text-sm text-zinc-300 mt-1">
                                Connect Google OAuth to upload videos and Shorts. Unaudited apps stay private until Google audit.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('youtube.redirect') }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition">
                                {{ $hasYouTubeAccount ? 'Reconnect YouTube' : 'Connect YouTube' }}
                            </a>

                            @if ($hasYouTubeAccount)
                                <form method="POST" action="{{ route('youtube.disconnect') }}"
                                      onsubmit="return confirm('Disconnect YouTube?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-zinc-900 border border-zinc-600 rounded-md font-semibold text-xs text-zinc-200 uppercase tracking-widest hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition">
                                        Disconnect
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($youtubeChannels->isEmpty())
                        <p class="text-sm text-zinc-400 border-t border-zinc-800 pt-4">
                            No YouTube channels linked yet.
                        </p>
                    @else
                        <ul class="divide-y divide-zinc-800 border-t border-zinc-800">
                            @foreach ($youtubeChannels as $channel)
                                <li class="py-4 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if ($channel->picture_url)
                                            <img src="{{ $channel->picture_url }}" alt="" class="h-10 w-10 rounded-full object-cover">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-zinc-700 flex items-center justify-center text-sm font-medium text-zinc-300">
                                                YT
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-medium text-zinc-100 truncate">{{ $channel->name }}</div>
                                            <div class="text-sm text-zinc-400 truncate">
                                                YouTube · ID {{ $channel->page_id }}
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="bg-zinc-900 border border-zinc-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-zinc-100 space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium">TikTok Accounts</h3>
                            <p class="text-sm text-zinc-300 mt-1">
                                Connect TikTok Login Kit to publish videos. Until audit, posts are private (SELF_ONLY) and your TikTok account should be private while testing.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('tiktok.redirect') }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition">
                                {{ $hasTikTokAccount ? 'Reconnect TikTok' : 'Connect TikTok' }}
                            </a>

                            @if ($hasTikTokAccount)
                                <form method="POST" action="{{ route('tiktok.disconnect') }}"
                                      onsubmit="return confirm('Disconnect TikTok?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-zinc-900 border border-zinc-600 rounded-md font-semibold text-xs text-zinc-200 uppercase tracking-widest hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition">
                                        Disconnect
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($tiktokAccounts->isEmpty())
                        <p class="text-sm text-zinc-400 border-t border-zinc-800 pt-4">
                            No TikTok accounts linked yet.
                        </p>
                    @else
                        <ul class="divide-y divide-zinc-800 border-t border-zinc-800">
                            @foreach ($tiktokAccounts as $account)
                                <li class="py-4 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if ($account->picture_url)
                                            <img src="{{ $account->picture_url }}" alt="" class="h-10 w-10 rounded-full object-cover">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-zinc-700 flex items-center justify-center text-sm font-medium text-zinc-300">
                                                TT
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-medium text-zinc-100 truncate">{{ $account->name }}</div>
                                            <div class="text-sm text-zinc-400 truncate">
                                                TikTok · ID {{ $account->page_id }}
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
