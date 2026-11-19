@php
    $tabs = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'];
    $fmt = fn ($value) => is_numeric($value) ? number_format((float) $value) : '—';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="kicker">Analytics</p>
                <h2 class="mt-2 font-semibold text-3xl tracking-tight text-[#1A1D23] leading-tight">{{ __('Insights') }}</h2>
                <p class="mt-2 text-[15px] text-[#5C534C]">
                    {{ $pageName ?? 'No account connected' }}
                    <span class="text-[#5C6570]">· {{ $rangeFrom->format('j M Y') }} – {{ $rangeTo->format('j M Y') }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <div class="inline-flex rounded-md border border-[#E4E7EC] bg-white p-0.5">
                    @foreach ($tabs as $key => $label)
                        <a href="{{ route('insights.index', ['platform' => $key, 'range' => $range]) }}"
                           class="rounded-lg px-3 py-2 text-[15px] font-semibold {{ $platform === $key ? 'bg-glory-500 text-white' : 'text-[#1A1D23] hover:text-[#1A1D23]' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <select onchange="window.location.href=this.value"
                        class="rounded-md border-[#E4E7EC] text-sm shadow-sm focus:border-glory-400 focus:ring-glory-400">
                    @foreach ($ranges as $days)
                        <option value="{{ route('insights.index', ['platform' => $platform, 'range' => $days]) }}"
                                @selected($range === $days)>
                            Last {{ $days }} days
                        </option>
                    @endforeach
                </select>

                <a href="{{ route('insights.index', ['platform' => $platform, 'range' => $range, 'fresh' => 1]) }}"
                   class="btn-primary">
                    Refresh
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if ($error)
                <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $error }}
                    @if ($platform === 'instagram')
                        <a href="{{ route('dashboard') }}" class="ml-1 font-medium underline">Reconnect Instagram</a>
                    @elseif ($platform === 'youtube')
                        <a href="{{ route('dashboard') }}" class="ml-1 font-medium underline">Reconnect YouTube</a>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                @foreach ([
                    'Views' => $summary['views'],
                    $platform === 'youtube' ? 'Videos' : 'Reach' => $platform === 'youtube'
                        ? $summary['total']
                        : $summary['reach'],
                    $platform === 'facebook' ? 'Followers views' : ($platform === 'youtube' ? 'Watch mins' : 'Posts') => $platform === 'facebook'
                        ? $summary['from_followers']
                        : ($platform === 'youtube'
                            ? (int) ($pageStats['watch_minutes'] ?? 0)
                            : $summary['total']),
                    $platform === 'facebook' ? 'Non-followers' : 'From SMM' => $platform === 'facebook'
                        ? $summary['from_non_followers']
                        : $summary['from_app'],
                    in_array($platform, ['instagram', 'youtube'], true) ? 'Likes' : 'Reactions' => $summary['reactions'],
                    'Comments' => $summary['comments'],
                    'Shares' => $summary['shares'],
                ] as $label => $value)
                    <div class="rounded-lg border border-[#E4E7EC] bg-white px-3 py-2.5">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-[#5C6570]">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold text-[#1A1D23]">{{ number_format($value) }}</div>
                    </div>
                @endforeach
            </div>

            @if ($pageStats)
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-[#E4E7EC] bg-white px-4 py-3 text-sm">
                    <span class="text-[#5C6570]">{{ $platform === 'youtube' ? 'Subscribers' : ($platform === 'instagram' ? 'Instagram followers' : 'Page followers') }}:
                        <strong class="text-[#1A1D23]">{{ $fmt($pageStats['followers']) }}</strong>
                    </span>
                    @if ($platform === 'facebook')
                        <span class="text-[#5C6570]">New follows ({{ $range }}d):
                            <strong class="text-[#1A1D23]">{{ $fmt($pageStats['new_follows']) }}</strong>
                        </span>
                        <span class="text-[#5C6570]">Page views ({{ $range }}d):
                            <strong class="text-[#1A1D23]">{{ $fmt($pageStats['page_views']) }}</strong>
                        </span>
                    @elseif ($platform === 'youtube')
                        <span class="text-[#5C6570]">Net subs ({{ $range }}d):
                            <strong class="text-[#1A1D23]">{{ $fmt($pageStats['new_follows']) }}</strong>
                        </span>
                        <span class="text-[#5C6570]">Channel views ({{ $range }}d):
                            <strong class="text-[#1A1D23]">{{ $fmt($pageStats['page_views']) }}</strong>
                        </span>
                    @endif
                    <span class="text-[#5C6570]">
                        {{ $summary['total'] }} posts · {{ $summary['from_app'] }} published from this app
                    </span>
                </div>
            @endif

            @if ($rows->isEmpty())
                <div class="rounded-lg border border-[#E4E7EC] bg-white p-10 text-center text-sm text-[#5C6570]">
                    No {{ $tabs[$platform] }} content in this range.
                </div>
            @else
                <div class="hidden overflow-hidden rounded-lg border border-[#E4E7EC] bg-white lg:block">
                    <table class="min-w-full divide-y divide-[#E4E7EC] text-sm">
                        <thead class="bg-[#F5F6F8] text-xs uppercase tracking-wide text-[#5C6570]">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-medium">Post</th>
                                <th class="px-3 py-2.5 text-right font-medium">Views</th>
                                <th class="px-3 py-2.5 text-right font-medium">Reach</th>
                                @if ($platform === 'facebook')
                                    <th class="px-3 py-2.5 text-right font-medium">Followers</th>
                                    <th class="px-3 py-2.5 text-right font-medium">Non-foll.</th>
                                @endif
                                <th class="px-3 py-2.5 text-right font-medium">{{ in_array($platform, ['instagram', 'youtube'], true) ? 'Likes' : 'Reactions' }}</th>
                                <th class="px-3 py-2.5 text-right font-medium">Comments</th>
                                <th class="px-3 py-2.5 text-right font-medium">Shares</th>
                                <th class="px-3 py-2.5 text-right font-medium">Published</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E4E7EC]">
                            @foreach ($rows as $row)
                                <tr class="hover:bg-[#F5F6F8]">
                                    <td class="max-w-md px-4 py-3">
                                        <div class="flex items-start gap-3">
                                            <div class="h-11 w-11 shrink-0 overflow-hidden rounded bg-[#F5F6F8]">
                                                @if ($row['thumbnail'])
                                                    <img src="{{ $row['thumbnail'] }}" alt="" class="h-11 w-11 object-cover">
                                                @else
                                                    <div class="flex h-11 w-11 items-center justify-center text-[10px] uppercase text-[#5C6570]">
                                                        {{ Str::limit($row['type'], 5, '') }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <div class="truncate font-medium text-[#1A1D23]">{{ $row['title'] }}</div>
                                                <div class="mt-0.5 flex items-center gap-2 text-xs text-[#5C6570]">
                                                    <span class="uppercase">{{ $row['type'] }}</span>
                                                    @if ($row['permalink'])
                                                        <a href="{{ $row['permalink'] }}" target="_blank" rel="noopener"
                                                           class="text-glory-500 hover:underline">View</a>
                                                    @endif
                                                    @if ($row['from_app'])
                                                        <span class="rounded bg-glory-50 px-1.5 py-0.5 text-[10px] font-medium text-glory-600">SMM</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold text-[#1A1D23]">{{ $fmt($row['views']) }}</td>
                                    <td class="px-3 py-3 text-right text-[#1A1D23]">{{ $fmt($row['reach']) }}</td>
                                    @if ($platform === 'facebook')
                                        <td class="px-3 py-3 text-right text-[#1A1D23]">{{ $fmt($row['from_followers']) }}</td>
                                        <td class="px-3 py-3 text-right text-[#1A1D23]">{{ $fmt($row['from_non_followers']) }}</td>
                                    @endif
                                    <td class="px-3 py-3 text-right text-[#1A1D23]">{{ $fmt($row['reactions']) }}</td>
                                    <td class="px-3 py-3 text-right text-[#1A1D23]">{{ $fmt($row['comments']) }}</td>
                                    <td class="px-3 py-3 text-right text-[#1A1D23]">{{ $fmt($row['shares']) }}</td>
                                    <td class="px-3 py-3 text-right text-xs text-[#5C6570]">
                                        {{ $row['published_at']?->format('d M H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 lg:hidden">
                    @foreach ($rows as $row)
                        <div class="rounded-lg border border-[#E4E7EC] bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-[#1A1D23]">{{ $row['title'] }}</div>
                                    <div class="mt-0.5 text-xs uppercase text-[#5C6570]">{{ $row['type'] }}</div>
                                </div>
                                <span class="whitespace-nowrap text-xs text-[#5C6570]">
                                    {{ $row['published_at']?->format('d M') }}
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs">
                                @foreach (['Views' => 'views', 'Reach' => 'reach', 'React.' => 'reactions', 'Shares' => 'shares'] as $label => $key)
                                    <div class="rounded bg-[#F5F6F8] py-2">
                                        <div class="text-[#5C6570]">{{ $label }}</div>
                                        <div class="font-semibold text-[#1A1D23]">{{ $fmt($row[$key]) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="text-xs text-[#5C6570]">
                Pulled live from your {{ match ($platform) {
                    'facebook' => 'Facebook Page',
                    'instagram' => 'Instagram account',
                    'youtube' => 'YouTube channel',
                    default => 'account',
                } }}, including content made outside this app.
                Cached for 10 minutes; hit Refresh for the latest.
            </p>
        </div>
    </div>
</x-app-layout>
