<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SocialPage;
use App\Services\FacebookService;
use App\Services\InstagramService;
use App\Services\YouTubeService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class InsightsController extends Controller
{
    /** @var array<int, int> */
    private const RANGES = [7, 28, 90];

    private const DEFAULT_RANGE = 28;

    private const CACHE_MINUTES = 45;

    public function index(
        Request $request,
        FacebookService $facebook,
        InstagramService $instagram,
        YouTubeService $youtube
    ): View
    {
        $platform = $this->platform($request->string('platform')->toString());
        $range = $this->range($request->integer('range', self::DEFAULT_RANGE));
        [$from, $to] = $this->rangeBounds($range);

        $page = $this->connectedPage($request, $platform);
        $error = null;
        $rows = collect();
        $pageStats = null;

        if ($platform === 'facebook' && $page) {
            try {
                $rows = $this->facebookRows($page, $from, $to, $request->boolean('fresh'));
                $pageStats = $this->facebookPageStats($page, $facebook, $from, $to);
            } catch (Throwable $e) {
                report($e);
                $error = $e->getMessage();
            }
        } elseif ($platform === 'instagram' && $page) {
            try {
                $rows = $this->instagramRows($page, $instagram, $from, $to, $request->boolean('fresh'));
                $pageStats = $this->instagramPageStats($page, $instagram, $from, $to);
            } catch (Throwable $e) {
                report($e);
                $error = $this->instagramError($e);
            }
        } elseif ($platform === 'youtube' && $page) {
            try {
                [$rows, $pageStats] = $this->youtubeRows($page, $youtube, $from, $to, $request->boolean('fresh'));
            } catch (Throwable $e) {
                report($e);
                $error = $this->youtubeError($e);
            }
        } else {
            $rows = $this->localRows($request, $platform, $from, $to);
        }

        return view('insights.index', [
            'platform' => $platform,
            'range' => $range,
            'ranges' => self::RANGES,
            'rangeFrom' => $from,
            'rangeTo' => $to,
            'pageName' => $page?->name ?? $pageStats['channel_title'] ?? null,
            'pageStats' => $pageStats,
            'rows' => $rows,
            'summary' => $this->buildSummary($rows),
            'error' => $error,
        ]);
    }

    public function refreshAll(Request $request): RedirectResponse
    {
        $platform = $this->platform($request->string('platform')->toString());
        $range = $this->range($request->integer('range', self::DEFAULT_RANGE));

        $page = $this->connectedPage($request, $platform);

        if ($page) {
            [$from, $to] = $this->rangeBounds($range);
            Cache::forget($this->cacheKey($page, $from, $to));
            Cache::forget($this->statsCacheKey($page, $from, $to));
        }

        return redirect()->route('insights.index', [
            'platform' => $platform,
            'range' => $range,
            'fresh' => 1,
        ]);
    }

    public function refreshPost(Request $request, Post $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        return $this->refreshAll($request);
    }

    /**
     * Live Page content from Meta, which includes posts made outside this app.
     */
    private function facebookRows(SocialPage $page, Carbon $from, Carbon $to, bool $fresh): Collection
    {
        $key = $this->cacheKey($page, $from, $to);

        if ($fresh) {
            Cache::forget($key);
        }

        $content = Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), function () use ($page, $from, $to) {
            return app(FacebookService::class)->getPageContent(
                $page->page_id,
                $page->access_token,
                $from->timestamp,
                $to->timestamp,
                25,
            );
        });

        $appPostIds = Post::query()
            ->where('social_page_id', $page->id)
            ->pluck('facebook_post_id')
            ->filter()
            ->map(fn ($id) => Str::afterLast($id, '_'))
            ->all();

        return collect($content)
            ->map(function (array $item) use ($appPostIds) {
                $insights = $item['insights'] ?? [];

                return [
                    'title' => $this->rowTitle($item['message'] ?? null),
                    'type' => $item['type'] ?? 'status',
                    'permalink' => $item['permalink_url'] ?? null,
                    'thumbnail' => $item['thumbnail_url'] ?? null,
                    'published_at' => $item['created_time'] ? Carbon::parse($item['created_time']) : null,
                    'views' => $this->pick($insights, ['post_media_view', 'post_video_views']),
                    'reach' => $this->pick($insights, [
                        'post_impressions_unique',
                        'post_impressions_organic_unique',
                        'post_total_media_view_unique',
                        'post_video_views_unique',
                    ]),
                    'from_followers' => $this->pick($insights, ['views_from_followers']),
                    'from_non_followers' => $this->pick($insights, ['views_from_non_followers']),
                    'reactions' => $item['reactions'] ?? 0,
                    'comments' => $item['comments'] ?? 0,
                    'shares' => $item['shares'] ?? 0,
                    'from_app' => in_array(Str::afterLast((string) $item['id'], '_'), $appPostIds, true),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['published_at']?->timestamp ?? 0)
            ->values();
    }

    /**
     * Live Instagram media and insights, including content not posted here.
     */
    private function instagramRows(
        SocialPage $page,
        InstagramService $instagram,
        Carbon $from,
        Carbon $to,
        bool $fresh
    ): Collection {
        $key = $this->cacheKey($page, $from, $to);

        if ($fresh) {
            Cache::forget($key);
        }

        $content = Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), function () use ($page, $instagram, $from, $to) {
            return $instagram->getAccountContent(
                $page->page_id,
                $page->access_token,
                $from->timestamp,
                $to->timestamp,
                25,
            );
        });

        $appMediaIds = Post::query()
            ->where('social_page_id', $page->id)
            ->pluck('facebook_post_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();

        return collect($content)
            ->map(function (array $item) use ($appMediaIds) {
                $insights = $item['insights'] ?? [];
                $productType = $item['media_product_type'] ?? '';
                $mediaType = $item['media_type'] ?? 'MEDIA';

                return [
                    'title' => $this->rowTitle($item['caption'] ?? null),
                    'type' => $productType === 'REELS' ? 'REEL' : $mediaType,
                    'permalink' => $item['permalink'] ?? null,
                    'thumbnail' => $item['thumbnail_url'] ?? null,
                    'published_at' => ! empty($item['timestamp']) ? Carbon::parse($item['timestamp']) : null,
                    'views' => $this->pick($insights, ['views']),
                    'reach' => $this->pick($insights, ['reach']),
                    'from_followers' => null,
                    'from_non_followers' => null,
                    'reactions' => $this->pick($insights, ['likes']) ?? ($item['like_count'] ?? 0),
                    'comments' => $this->pick($insights, ['comments']) ?? ($item['comments_count'] ?? 0),
                    'shares' => $this->pick($insights, ['shares']),
                    'from_app' => in_array((string) ($item['id'] ?? ''), $appMediaIds, true),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['published_at']?->timestamp ?? 0)
            ->values();
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function youtubeRows(
        SocialPage $page,
        YouTubeService $youtube,
        Carbon $from,
        Carbon $to,
        bool $fresh
    ): array {
        $key = $this->cacheKey($page, $from, $to);

        if ($fresh) {
            Cache::forget($key);
        }

        $payload = Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), function () use ($page, $youtube, $from, $to) {
            $token = $youtube->resolveAccessToken($page);

            return $youtube->getAccountInsights(
                $token,
                $page->page_id,
                $from->timestamp,
                $to->timestamp,
            );
        });

        $appVideoIds = Post::query()
            ->where('social_page_id', $page->id)
            ->pluck('facebook_post_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();

        $rows = collect($payload['content'] ?? [])
            ->map(function (array $item) use ($appVideoIds) {
                return [
                    'title' => $this->rowTitle($item['title'] ?? null),
                    'type' => $item['type'] ?? 'VIDEO',
                    'permalink' => $item['permalink'] ?? null,
                    'thumbnail' => $item['thumbnail_url'] ?? null,
                    'published_at' => ! empty($item['published_at']) ? Carbon::parse($item['published_at']) : null,
                    'views' => is_numeric($item['views'] ?? null) ? (float) $item['views'] : null,
                    'reach' => null,
                    'from_followers' => null,
                    'from_non_followers' => null,
                    'reactions' => is_numeric($item['likes'] ?? null) ? (float) $item['likes'] : null,
                    'comments' => is_numeric($item['comments'] ?? null) ? (float) $item['comments'] : null,
                    'shares' => is_numeric($item['shares'] ?? null) ? (float) $item['shares'] : null,
                    'from_app' => in_array((string) ($item['id'] ?? ''), $appVideoIds, true),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['published_at']?->timestamp ?? 0)
            ->values();

        $summary = $payload['summary'] ?? [];
        $gained = (int) ($summary['subscribersGained'] ?? 0);
        $lost = (int) ($summary['subscribersLost'] ?? 0);

        $pageStats = [
            'followers' => $summary['subscribers'] ?? null,
            'new_follows' => $gained - $lost,
            'page_views' => $summary['views'] ?? null,
            'channel_title' => $summary['channel_title'] ?? null,
            'watch_minutes' => $summary['estimatedMinutesWatched'] ?? null,
        ];

        return [$rows, $pageStats];
    }

    /**
     * Fallback for platforms without a content API wired up yet.
     */
    private function localRows(Request $request, string $platform, Carbon $from, Carbon $to): Collection
    {
        return $request->user()
            ->posts()
            ->with('socialPage')
            ->where('status', 'published')
            ->whereHas('socialPage', fn ($query) => $query->where('provider', $platform))
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Post $post) => [
                'title' => $this->rowTitle($post->title ?: $post->message),
                'type' => $post->post_format === 'reel' ? 'reel' : ($post->media_type ?: 'status'),
                'permalink' => $post->insightValue('permalink_url'),
                'thumbnail' => $post->insightValue('thumbnail_url'),
                'published_at' => $post->published_at ?? $post->created_at,
                'views' => $post->metric('views'),
                'reach' => $post->metric('reach'),
                'from_followers' => $post->metric('from_followers'),
                'from_non_followers' => $post->metric('from_non_followers'),
                'reactions' => $post->metric('reactions') ?? 0,
                'comments' => $post->metric('comments') ?? 0,
                'shares' => $post->metric('shares') ?? 0,
                'from_app' => true,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function facebookPageStats(SocialPage $page, FacebookService $facebook, Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            $this->statsCacheKey($page, $from, $to),
            now()->addMinutes(self::CACHE_MINUTES),
            function () use ($page, $facebook, $from, $to) {
                $stats = ['followers' => null, 'new_follows' => null, 'page_views' => null];

                try {
                    $insights = $facebook->getPageSummaryInsights(
                        $page->page_id,
                        $page->access_token,
                        $from->timestamp,
                        $to->timestamp,
                    );

                    $stats['followers'] = $insights['followers_count'] ?? null;
                    $stats['new_follows'] = $insights['page_daily_follows_unique']
                        ?? $insights['page_daily_follows']
                        ?? $insights['page_follows']
                        ?? null;
                    $stats['page_views'] = $insights['page_views_total'] ?? $insights['page_media_view'] ?? null;
                } catch (Throwable $e) {
                    report($e);
                }

                return $stats;
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function instagramPageStats(SocialPage $page, InstagramService $instagram, Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            $this->statsCacheKey($page, $from, $to),
            now()->addMinutes(self::CACHE_MINUTES),
            function () use ($page, $instagram) {
                $stats = ['followers' => null, 'new_follows' => null, 'page_views' => null];

                try {
                    $summary = $instagram->getAccountSummary($page->page_id, $page->access_token);
                    $stats['followers'] = $summary['followers_count'] ?? null;
                } catch (Throwable $e) {
                    report($e);
                }

                return $stats;
            }
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function buildSummary(Collection $rows): array
    {
        $sum = fn (string $key) => (int) $rows->sum(fn (array $row) => (float) ($row[$key] ?? 0));

        return [
            'views' => $sum('views'),
            'reach' => $sum('reach'),
            'from_followers' => $sum('from_followers'),
            'from_non_followers' => $sum('from_non_followers'),
            'reactions' => $sum('reactions'),
            'comments' => $sum('comments'),
            'shares' => $sum('shares'),
            'total' => $rows->count(),
            'from_app' => $rows->where('from_app', true)->count(),
        ];
    }

    private function connectedPage(Request $request, string $platform): ?SocialPage
    {
        return $request->user()
            ->socialPages()
            ->where('provider', $platform)
            ->where('is_connected', true)
            ->orderBy('name')
            ->first();
    }

    private function cacheKey(SocialPage $page, Carbon $from, Carbon $to): string
    {
        return sprintf('insights:%s:%d:%s:%s', $page->provider, $page->id, $from->toDateString(), $to->toDateString());
    }

    private function statsCacheKey(SocialPage $page, Carbon $from, Carbon $to): string
    {
        return sprintf('insights-stats:%s:%d:%s:%s', $page->provider, $page->id, $from->toDateString(), $to->toDateString());
    }

    private function instagramError(Throwable $error): string
    {
        $message = $error->getMessage();

        if (str_contains($message, 'permission')
            || str_contains($message, 'OAuth')
            || str_contains($message, 'code\":190')) {
            return 'Instagram Insights permission is missing. Reconnect Instagram from Dashboard, then return here.';
        }

        return $message;
    }

    private function youtubeError(Throwable $error): string
    {
        $message = $error->getMessage();

        if (str_contains($message, 'insufficientPermissions')
            || str_contains($message, 'ACCESS_TOKEN_SCOPE_INSUFFICIENT')
            || str_contains($message, 'yt-analytics')
            || str_contains($message, 'insufficient authentication scopes')) {
            return 'YouTube Analytics permission is missing. Reconnect YouTube from Dashboard after enabling yt-analytics.readonly.';
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $insights
     * @param  array<int, string>  $keys
     */
    private function pick(array $insights, array $keys): ?float
    {
        $best = null;

        foreach ($keys as $key) {
            $value = $insights[$key] ?? null;

            if (is_numeric($value)) {
                $best = max($best ?? 0, (float) $value);
            }
        }

        return $best;
    }

    private function rowTitle(?string $message): string
    {
        $line = trim(Str::before(trim((string) $message), "\n"));

        return $line !== '' ? Str::limit($line, 70) : 'Untitled';
    }

    private function platform(?string $value): string
    {
        return in_array($value, ['facebook', 'instagram', 'youtube'], true) ? $value : 'facebook';
    }

    private function range(int $value): int
    {
        return in_array($value, self::RANGES, true) ? $value : self::DEFAULT_RANGE;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangeBounds(int $days): array
    {
        return [now()->subDays($days - 1)->startOfDay(), now()->endOfDay()];
    }
}
