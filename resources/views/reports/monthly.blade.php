<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="kicker">Reporting</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[#1A1D23] sm:text-4xl">Monthly postings</h2>
                <p class="mt-2 max-w-xl text-[15px] leading-relaxed text-[#5C534C]">
                    Working days are Monday–Friday. A day counts as posted if at least one published post went out that day.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('reports.monthly', ['month' => $prevMonth]) }}" class="btn-secondary">← Prev</a>
                <div class="min-w-[9.5rem] rounded-full border border-[#C9B8AD] bg-[#FFFCF9] px-4 py-2.5 text-center text-sm font-semibold text-[#1A1D23]">
                    {{ $monthLabel }}
                </div>
                <a href="{{ route('reports.monthly', ['month' => $nextMonth]) }}" class="btn-secondary">Next →</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                <div class="panel px-5 py-5">
                    <div class="kicker">Working days</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-[#1A1D23]">{{ $workingDaysTotal }}</div>
                    <p class="mt-1 text-xs text-[#6F655C]">Mon–Fri in {{ $month->format('M') }}</p>
                </div>
                <div class="panel px-5 py-5">
                    <div class="kicker">Posted</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-emerald-700">{{ $postedDays }}</div>
                    <p class="mt-1 text-xs text-[#6F655C]">{{ $totalPosts }} post{{ $totalPosts === 1 ? '' : 's' }} published</p>
                </div>
                <div class="panel px-5 py-5">
                    <div class="kicker">Missed</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-[#C0352B]">{{ $missedDays }}</div>
                    <p class="mt-1 text-xs text-[#6F655C]">
                        @if ($isCurrentMonth)
                            of {{ $elapsedWorkingDays }} elapsed working day{{ $elapsedWorkingDays === 1 ? '' : 's' }}
                        @else
                            working days with no post
                        @endif
                    </p>
                </div>
                <div class="panel px-5 py-5">
                    <div class="kicker">Coverage</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-[#1A1D23]">
                        {{ $coveragePercent === null ? '—' : $coveragePercent.'%' }}
                    </div>
                    <p class="mt-1 text-xs text-[#6F655C]">Posted ÷ elapsed working days</p>
                </div>
                <div class="panel col-span-2 px-5 py-5 lg:col-span-1">
                    <div class="kicker">Upcoming</div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-[#1A1D23]">{{ $upcomingDays }}</div>
                    <p class="mt-1 text-xs text-[#6F655C]">Future Mon–Fri left</p>
                </div>
            </div>

            <section class="panel">
                <div class="border-b border-[#D4C3B8] px-6 py-5">
                    <h3 class="text-lg font-semibold tracking-tight text-[#1A1D23]">Working day log</h3>
                    <p class="mt-1 text-sm text-[#5C534C]">Each weekday in {{ $monthLabel }}</p>
                </div>
                <ul class="divide-y divide-[#D4C3B8] px-2 sm:px-4">
                    @forelse ($days as $day)
                        <li class="flex items-center justify-between gap-3 px-4 py-3.5">
                            <div class="min-w-0">
                                <div class="font-semibold text-[#1A1D23]">{{ $day['label'] }}</div>
                                @if ($day['status'] === 'posted')
                                    <div class="text-xs text-[#6F655C]">{{ $day['post_count'] }} published post{{ $day['post_count'] === 1 ? '' : 's' }}</div>
                                @elseif ($day['status'] === 'upcoming')
                                    <div class="text-xs text-[#6F655C]">Not yet due</div>
                                @else
                                    <div class="text-xs text-[#6F655C]">No published post</div>
                                @endif
                            </div>
                            @if ($day['status'] === 'posted')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-100">Posted</span>
                            @elseif ($day['status'] === 'upcoming')
                                <span class="rounded-full bg-[#F0EBE6] px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#6B635C] ring-1 ring-[#E4D9D1]">Upcoming</span>
                            @else
                                <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700 ring-1 ring-red-100">Missed</span>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-sm text-[#5C534C]">No working days in this month.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
