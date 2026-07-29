<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Insights') }}
            </h2>

            <form method="GET" action="{{ route('insights.index') }}" class="flex items-center gap-2">
                <select name="platform"
                        onchange="this.form.submit()"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="facebook" @selected($platform === 'facebook')>Facebook</option>
                    <option value="instagram" @selected($platform === 'instagram')>Instagram</option>
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ ucfirst($platform) }} performance
                            @isset($summary['page_name'])
                                <span class="text-sm font-normal text-gray-500">· {{ $summary['page_name'] }}</span>
                            @endisset
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">
                            @if ($platform === 'facebook')
                                Summary from your published posts. Click Refresh to pull latest Meta numbers.
                            @else
                                Instagram insights UI is ready. Bulk Meta insights for IG need insights permission next.
                            @endif
                        </p>
                    </div>

                    @if ($platform === 'facebook')
                        <form method="POST" action="{{ route('insights.refresh') }}">
                            @csrf
                            <input type="hidden" name="platform" value="facebook">
                            <x-primary-button type="submit">Refresh all insights</x-primary-button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Total views</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($summary['views']) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Reach</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($summary['reach']) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">From followers</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($summary['from_followers']) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">From non-followers</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($summary['from_non_followers']) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Followers</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">
                        {{ $summary['followers'] !== null ? number_format((float) $summary['followers']) : '—' }}
                    </div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">
                        {{ $summary['follows_today'] !== null ? 'Follows (recent)' : 'Page views' }}
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">
                        @if ($summary['follows_today'] !== null)
                            {{ number_format((float) $summary['follows_today']) }}
                        @elseif ($summary['page_views'] !== null)
                            {{ number_format((float) $summary['page_views']) }}
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>

            <div class="text-sm text-gray-500">
                Showing {{ $summary['posts_total'] }} published {{ $platform }} post(s)
                · {{ $summary['posts_with_insights'] }} with insights loaded
            </div>

            @if ($posts->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    No published {{ ucfirst($platform) }} posts yet.
                    <a href="{{ route('posts.create') }}" class="text-indigo-600 underline ml-1">Create a post</a>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($posts as $post)
                        @php
                            $views = $post->insightValue('post_media_view') ?? $post->insightValue('post_video_views');
                            $reach = $post->insightValue('post_total_media_view_unique') ?? $post->insightValue('post_video_views_unique');
                            $fromFollowers = $post->insightValue('views_from_followers');
                            $fromNonFollowers = $post->insightValue('views_from_non_followers');
                            $thumb = $post->media_path ? asset('storage/'.$post->media_path) : null;
                            $isImage = $post->media_type === 'image' && $post->media_path;
                        @endphp

                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg flex flex-col">
                            <div class="h-40 bg-gray-100 flex items-center justify-center overflow-hidden">
                                @if ($isImage)
                                    <img src="{{ $thumb }}" alt="" class="w-full h-40 object-cover">
                                @elseif ($post->media_type === 'video' || $post->post_format === 'reel')
                                    <div class="text-sm text-gray-500">
                                        {{ strtoupper($post->post_format === 'reel' ? 'Reel' : 'Video') }}
                                    </div>
                                @else
                                    <div class="text-sm text-gray-500 px-4 text-center">
                                        {{ \Illuminate\Support\Str::limit($post->message ?: 'Text post', 80) }}
                                    </div>
                                @endif
                            </div>

                            <div class="p-4 flex-1 flex flex-col gap-3">
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="text-sm font-medium text-gray-900 truncate">
                                            {{ $post->socialPage?->name ?? 'Account' }}
                                            @if ($post->post_format === 'reel')
                                                <span class="text-xs text-indigo-600">REEL</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-400 whitespace-nowrap">
                                            {{ $post->published_at?->diffForHumans() ?? $post->created_at?->diffForHumans() }}
                                        </div>
                                    </div>
                                    @if ($post->title)
                                        <div class="mt-1 text-sm font-medium text-gray-800 truncate">{{ $post->title }}</div>
                                    @endif
                                    <p class="mt-1 text-sm text-gray-600 line-clamp-2">
                                        {{ \Illuminate\Support\Str::limit($post->message ?: '['.$post->media_type.']', 100) }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded bg-gray-50 p-2">
                                        <div class="text-gray-500">Views</div>
                                        <div class="font-semibold text-gray-900">{{ is_numeric($views) ? number_format((float) $views) : '—' }}</div>
                                    </div>
                                    <div class="rounded bg-gray-50 p-2">
                                        <div class="text-gray-500">Reach</div>
                                        <div class="font-semibold text-gray-900">{{ is_numeric($reach) ? number_format((float) $reach) : '—' }}</div>
                                    </div>
                                    <div class="rounded bg-gray-50 p-2">
                                        <div class="text-gray-500">Followers</div>
                                        <div class="font-semibold text-gray-900">{{ is_numeric($fromFollowers) ? number_format((float) $fromFollowers) : '—' }}</div>
                                    </div>
                                    <div class="rounded bg-gray-50 p-2">
                                        <div class="text-gray-500">Non-followers</div>
                                        <div class="font-semibold text-gray-900">{{ is_numeric($fromNonFollowers) ? number_format((float) $fromNonFollowers) : '—' }}</div>
                                    </div>
                                </div>

                                <div class="mt-auto flex items-center justify-between text-xs text-gray-400">
                                    <span>
                                        @if ($post->insights_fetched_at)
                                            Updated {{ $post->insights_fetched_at->diffForHumans() }}
                                        @else
                                            No insights yet
                                        @endif
                                    </span>

                                    @if ($platform === 'facebook')
                                        <form method="POST" action="{{ route('insights.posts.refresh', $post) }}">
                                            @csrf
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-800">Refresh</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
