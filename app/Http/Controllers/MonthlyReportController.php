<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MonthlyReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $month = $this->resolveMonth($request->string('month')->toString());
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $today = now()->startOfDay();

        $workingDays = $this->workingDaysInRange($monthStart, $monthEnd);
        $evaluatedThrough = $monthEnd->lt($today) ? $monthEnd : ($monthStart->gt($today) ? null : $today);

        $elapsedWorkingDays = $evaluatedThrough
            ? $this->workingDaysInRange($monthStart, $evaluatedThrough)
            : collect();

        $postedDates = $this->postedDates($request, $monthStart, $monthEnd);
        $postedWorkingDays = $elapsedWorkingDays->filter(fn (Carbon $day) => $postedDates->has($day->toDateString()));
        $missedWorkingDays = $elapsedWorkingDays->reject(fn (Carbon $day) => $postedDates->has($day->toDateString()));
        $upcomingWorkingDays = $workingDays->filter(fn (Carbon $day) => $day->gt($today));

        $days = $workingDays->map(function (Carbon $day) use ($postedDates, $today) {
            $key = $day->toDateString();
            $isFuture = $day->gt($today);
            $posted = $postedDates->has($key);

            return [
                'date' => $day,
                'label' => $day->format('D j M'),
                'status' => $isFuture ? 'upcoming' : ($posted ? 'posted' : 'missed'),
                'post_count' => (int) ($postedDates->get($key) ?? 0),
            ];
        });

        $elapsedCount = $elapsedWorkingDays->count();
        $postedCount = $postedWorkingDays->count();
        $missedCount = $missedWorkingDays->count();

        // Weekend days/posts show in counts only — never in required working days or missed.
        [$weekdayPosts, $weekendPosts] = $this->splitPostCountsByWeekpart($postedDates);
        $postedWeekendDays = $postedDates->keys()
            ->filter(fn (string $date) => Carbon::parse($date)->isWeekend())
            ->count();

        return view('reports.monthly', [
            'month' => $monthStart,
            'monthLabel' => $monthStart->format('F Y'),
            'prevMonth' => $monthStart->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $monthStart->copy()->addMonth()->format('Y-m'),
            'workingDaysTotal' => $workingDays->count(),
            'elapsedWorkingDays' => $elapsedCount,
            'postedWorkingDays' => $postedCount,
            'postedWeekendDays' => $postedWeekendDays,
            'postedDaysTotal' => $postedCount + $postedWeekendDays,
            'missedDays' => $missedCount,
            'upcomingDays' => $upcomingWorkingDays->count(),
            'coveragePercent' => $elapsedCount > 0 ? round(($postedCount / $elapsedCount) * 100) : null,
            'totalPosts' => $weekdayPosts + $weekendPosts,
            'weekdayPosts' => $weekdayPosts,
            'weekendPosts' => $weekendPosts,
            'days' => $days,
            'isCurrentMonth' => $monthStart->isSameMonth($today),
        ]);
    }

    /**
     * @param  Collection<string, int>  $postedDates
     * @return array{0: int, 1: int}
     */
    private function splitPostCountsByWeekpart(Collection $postedDates): array
    {
        $weekdayPosts = 0;
        $weekendPosts = 0;

        foreach ($postedDates as $date => $count) {
            $day = Carbon::parse($date)->startOfDay();
            if ($day->isWeekend()) {
                $weekendPosts += (int) $count;
            } else {
                $weekdayPosts += (int) $count;
            }
        }

        return [$weekdayPosts, $weekendPosts];
    }

    private function resolveMonth(string $value): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function workingDaysInRange(Carbon $from, Carbon $to): Collection
    {
        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->filter(fn (Carbon $day) => $day->isWeekday())
            ->values();
    }

    /**
     * Distinct calendar dates with published posts → post counts.
     *
     * @return Collection<string, int>
     */
    private function postedDates(Request $request, Carbon $monthStart, Carbon $monthEnd): Collection
    {
        return $request->user()
            ->posts()
            ->where('status', 'published')
            ->where(function ($query) use ($monthStart, $monthEnd) {
                $query->whereBetween('published_at', [$monthStart, $monthEnd])
                    ->orWhere(function ($fallback) use ($monthStart, $monthEnd) {
                        $fallback->whereNull('published_at')
                            ->whereBetween('created_at', [$monthStart, $monthEnd]);
                    });
            })
            ->get(['published_at', 'created_at'])
            ->groupBy(fn ($post) => ($post->published_at ?? $post->created_at)->timezone(config('app.timezone'))->toDateString())
            ->map->count();
    }
}
