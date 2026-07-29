@php
    $tabs = ['facebook' => 'Facebook', 'instagram' => 'Instagram'];
    $fmt = fn ($value) => is_numeric($value) ? number_format((float) $value) : '—';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Insights') }}</h2>
                @if ($page)
                    <p class="text-sm text-gray-500">{{ $page['name'] }}</p>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <div class="inline-flex rounded-md border border-gray-300 bg-white p-0.5">
                    @foreach ($tabs as $key => $label)
                        <a href="{{ route('insights.index', ['platform' => $key, 'range' => $range]) }}"
                           class="px-3 py-1.5 text-sm rounded {{ $platform === $key ? 'bg-gray-900 text-white' : 'text-gray-600 hover:text-gray-900' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <select onchange="window.location.href=this.value"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($ranges as $days)
                        <option value="{{ route('insights.index', ['platform' => $platform, 'range' => $days]) }}"
                                @selected($range === $days)>
                            Last {{ $days }} days
                        </option>
                    @endforeach
                </select>

                @if ($platform === 'facebook')
                    <form method="POST" action="{{ route('insights.refresh') }}">
                        @csrf
                        <input type="hidden" name="platform" value="facebook">
                        <input type="hidden" name="range" value="{{ $range }}">
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Refresh all
                        </button>
                    </form>
                @endif
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-500">
            {{ $rangeFrom->format('j M Y') }} – {{ $rangeTo->format('j M Y') }}
        </p>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                @foreach ([
                    'Views' => $summary['views'],
                    'Reach' => $summary['reach'],
                    'Followers views' => $summary['from_followers'],
                    'Non-followers' => $summary['from_non_followers'],
                    'Reactions' => $summary['reactions'],
                    'Comments' => $summary['comments'],
                    'Shares' => $summary['shares'],
                ] as $label => $value)
                    <div class="rounded-lg border border-gray-200 bg-white px-3 py-2.5">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-gray-500">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($value) }}</div>
                    </div>
                @endforeach
            </div>

            @if ($page)
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm">
                    <span class="text-gray-500">Page followers:
                        <strong class="text-gray-900">{{ $fmt($page['followers']) }}</strong>
                    </span>
                    <span class="text-gray-500">New follows ({{ $range }}d):
                        <strong class="text-gray-900">{{ $fmt($page['new_follows']) }}</strong>
                    </span>
                    <span class="text-gray-500">Page views ({{ $range }}d):
                        <strong class="text-gray-900">{{ $fmt($page['page_views']) }}</strong>
                    </span>
                    <span class="text-gray-400">{{ $summary['with_insights'] }}/{{ $summary['total'] }} posts in range</span>
                </div>
            @endif

            @if ($posts->isEmpty())
                <div class="rounded-lg border border-gray-200 bg-white p-10 text-center text-sm text-gray-500">
                    No published {{ $tabs[$platform] }} posts yet.
                    <a href="{{ route('posts.create') }}" class="ml-1 text-indigo-600 underline">Create a post</a>
                </div>
            @else
                <div class="hidden overflow-hidden rounded-lg border border-gray-200 bg-white lg:block">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-medium">Post</th>
                                <th class="px-3 py-2.5 text-right font-medium">Views</th>
                                <th class="px-3 py-2.5 text-right font-medium">Reach</th>
                                <th class="px-3 py-2.5 text-right font-medium">Followers</th>
                                <th class="px-3 py-2.5 text-right font-medium">Non-foll.</th>
                                <th class="px-3 py-2.5 text-right font-medium">Reactions</th>
                                <th class="px-3 py-2.5 text-right font-medium">Comments</th>
                                <th class="px-3 py-2.5 text-right font-medium">Shares</th>
                                <th class="px-3 py-2.5 text-right font-medium">Published</th>
                                <th class="px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($posts as $post)
                                @php
                                    $thumb = $post->insightValue('thumbnail_url')
                                        ?: ($post->media_type === 'image' && $post->media_path ? asset('storage/'.$post->media_path) : null);
                                    $permalink = $post->insightValue('permalink_url');
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="max-w-md px-4 py-3">
                                        <div class="flex items-start gap-3">
                                            <div class="h-11 w-11 shrink-0 overflow-hidden rounded bg-gray-100">
                                                @if ($thumb)
                                                    <img src="{{ $thumb }}" alt="" class="h-11 w-11 object-cover">
                                                @else
                                                    <div class="flex h-11 w-11 items-center justify-center text-[10px] uppercase text-gray-400">
                                                        {{ $post->post_format === 'reel' ? 'Reel' : $post->media_type }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <div class="truncate font-medium text-gray-900">
                                                    {{ $post->title ?: \Illuminate\Support\Str::limit($post->message ?: 'Untitled', 60) }}
                                                </div>
                                                <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                                                    <span class="uppercase">{{ $post->post_format === 'reel' ? 'Reel' : ($post->media_type ?: 'text') }}</span>
                                                    @if ($permalink)
                                                        <a href="{{ $permalink }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">View</a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold text-gray-900">{{ $fmt($post->metric('views')) }}</td>
                                    <td class="px-3 py-3 text-right text-gray-700">{{ $fmt($post->metric('reach')) }}</td>
                                    <td class="px-3 py-3 text-right text-gray-700">{{ $fmt($post->metric('from_followers')) }}</td>
                                    <td class="px-3 py-3 text-right text-gray-700">{{ $fmt($post->metric('from_non_followers')) }}</td>
                                    <td class="px-3 py-3 text-right text-gray-700">{{ $fmt($post->metric('reactions')) }}</td>
                                    <td class="px-3 py-3 text-right text-gray-700">{{ $fmt($post->metric('comments')) }}</td>
                                    <td class="px-3 py-3 text-right text-gray-700">{{ $fmt($post->metric('shares')) }}</td>
                                    <td class="px-3 py-3 text-right text-xs text-gray-500">
                                        {{ ($post->published_at ?? $post->created_at)?->format('d M H:i') }}
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <form method="POST" action="{{ route('insights.posts.refresh', $post) }}">
                                            @csrf
                                            <input type="hidden" name="range" value="{{ $range }}">
                                            <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800">Refresh</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 lg:hidden">
                    @foreach ($posts as $post)
                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-gray-900">
                                        {{ $post->title ?: \Illuminate\Support\Str::limit($post->message ?: 'Untitled', 60) }}
                                    </div>
                                    <div class="mt-0.5 text-xs uppercase text-gray-500">
                                        {{ $post->post_format === 'reel' ? 'Reel' : ($post->media_type ?: 'text') }}
                                    </div>
                                </div>
                                <span class="whitespace-nowrap text-xs text-gray-400">
                                    {{ ($post->published_at ?? $post->created_at)?->format('d M') }}
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs">
                                @foreach (['Views' => 'views', 'Reach' => 'reach', 'React.' => 'reactions', 'Shares' => 'shares'] as $label => $key)
                                    <div class="rounded bg-gray-50 py-2">
                                        <div class="text-gray-500">{{ $label }}</div>
                                        <div class="font-semibold text-gray-900">{{ $fmt($post->metric($key)) }}</div>
                                    </div>
                                @endforeach
                            </div>

                            <form method="POST" action="{{ route('insights.posts.refresh', $post) }}" class="mt-3 text-right">
                                @csrf
                                <input type="hidden" name="range" value="{{ $range }}">
                                <button type="submit" class="text-xs text-indigo-600">Refresh</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="text-xs text-gray-400">
                Showing posts published in the selected range. Per-post views are lifetime for that post; page follows/views are summed for the range.
            </p>
        </div>
    </div>
</x-app-layout>
