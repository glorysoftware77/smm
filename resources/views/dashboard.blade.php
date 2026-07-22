<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium">Facebook Pages</h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Connect your Facebook account to link Pages you manage.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('facebook.redirect') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                                {{ $hasFacebookAccount ? 'Reconnect Facebook' : 'Connect Facebook' }}
                            </a>

                            @if ($hasFacebookAccount)
                                <form method="POST" action="{{ route('facebook.sync') }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                                        Refresh Pages
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('facebook.disconnect') }}"
                                      onsubmit="return confirm('Disconnect Facebook and all linked pages?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                                        Disconnect
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($pages->isEmpty())
                        <p class="text-sm text-gray-500 border-t border-gray-100 pt-4">
                            No Facebook Pages connected yet.
                        </p>
                    @else
                        <ul class="divide-y divide-gray-100 border-t border-gray-100">
                            @foreach ($pages as $page)
                                <li class="py-4 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if ($page->picture_url)
                                            <img src="{{ $page->picture_url }}" alt="" class="h-10 w-10 rounded-full object-cover">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-medium text-gray-600">
                                                {{ strtoupper(substr($page->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900 truncate">{{ $page->name }}</div>
                                            <div class="text-sm text-gray-500 truncate">
                                                {{ $page->category ?: 'Facebook Page' }} · ID {{ $page->page_id }}
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('facebook.pages.disconnect', $page) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                                            Remove
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div>
                        <h3 class="text-lg font-medium">Instagram Accounts</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Linked automatically from your Facebook Pages after reconnect (needs Instagram permissions).
                        </p>
                    </div>

                    @if ($instagramAccounts->isEmpty())
                        <p class="text-sm text-gray-500 border-t border-gray-100 pt-4">
                            No Instagram accounts linked yet. Reconnect Facebook after adding Instagram permissions, then click Refresh Pages.
                        </p>
                    @else
                        <ul class="divide-y divide-gray-100 border-t border-gray-100">
                            @foreach ($instagramAccounts as $account)
                                <li class="py-4 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if ($account->picture_url)
                                            <img src="{{ $account->picture_url }}" alt="" class="h-10 w-10 rounded-full object-cover">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-medium text-gray-600">
                                                IG
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900 truncate">{{ $account->name }}</div>
                                            <div class="text-sm text-gray-500 truncate">
                                                Instagram · ID {{ $account->page_id }}
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('facebook.pages.disconnect', $account) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                                            Remove
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
