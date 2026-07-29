<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\FacebookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class InsightsController extends Controller
{
    /**
     * Stop auto-fetching once a page load has spent this long on Graph calls.
     */
    private const AUTO_FETCH_BUDGET_SECONDS = 12;

    public function index(Request $request, FacebookService $facebook): View
    {
        $platform = $this->platform($request->string('platform')->toString());

        $posts = $this->publishedPosts($request, $platform, 60);

        if ($platform === 'facebook') {
            $this->autoFetchMissingInsights($posts, $facebook);
        }

        return view('insights.index', [
            'platform' => $platform,
            'posts' => $posts,
            'summary' => $this->buildSummary($posts),
            'page' => $this->pageSummary($request, $platform, $facebook),
        ]);
    }

    public function refreshAll(Request $request, FacebookService $facebook): RedirectResponse
    {
        $platform = $this->platform($request->string('platform')->toString());

        if ($platform !== 'facebook') {
            return $this->back($platform, 'error', 'Bulk refresh is available for Facebook only right now.');
        }

        $posts = $this->publishedPosts($request, 'facebook', 30);

        $updated = 0;
        $failed = 0;

        foreach ($posts as $post) {
            try {
                $this->refreshPostInsights($post, $facebook) ? $updated++ : $failed++;
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return $this->back('facebook', 'success', "Refreshed {$updated} post(s)".($failed ? ", {$failed} not ready yet." : '.'));
    }

    public function refreshPost(Request $request, Post $post, FacebookService $facebook): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        $platform = $post->socialPage?->provider ?? 'facebook';

        if ($platform !== 'facebook') {
            return $this->back($platform, 'error', 'Insights refresh is currently available for Facebook posts only.');
        }

        try {
            $ok = $this->refreshPostInsights($post, $facebook);

            return $this->back('facebook', $ok ? 'success' : 'error', $ok
                ? 'Insights updated.'
                : 'Insights not available yet for this post.');
        } catch (Throwable $e) {
            report($e);

            return $this->back('facebook', 'error', 'Insights failed: '.$e->getMessage());
        }
    }

    private function platform(?string $value): string
    {
        return in_array($value, ['facebook', 'instagram'], true) ? $value : 'facebook';
    }

    private function back(string $platform, string $key, string $message): RedirectResponse
    {
        return redirect()
            ->route('insights.index', ['platform' => $platform])
            ->with($key, $message);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Post>
     */
    private function publishedPosts(Request $request, string $platform, int $limit)
    {
        return $request->user()
            ->posts()
            ->with('socialPage')
            ->where('status', 'published')
            ->whereHas('socialPage', fn ($query) => $query->where('provider', $platform))
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Post>  $posts
     */
    private function autoFetchMissingInsights($posts, FacebookService $facebook): void
    {
        $startedAt = microtime(true);

        foreach ($posts as $post) {
            if ($post->insights_fetched_at !== null) {
                continue;
            }

            if (microtime(true) - $startedAt > self::AUTO_FETCH_BUDGET_SECONDS) {
                return;
            }

            try {
                $this->refreshPostInsights($post, $facebook);
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Post>  $posts
     * @return array<string, int>
     */
    private function buildSummary($posts): array
    {
        $summary = [
            'views' => 0,
            'reach' => 0,
            'from_followers' => 0,
            'from_non_followers' => 0,
            'reactions' => 0,
            'comments' => 0,
            'shares' => 0,
            'clicks' => 0,
            'with_insights' => 0,
            'total' => $posts->count(),
        ];

        foreach ($posts as $post) {
            if ($post->metric('views') !== null || $post->metric('reactions') !== null) {
                $summary['with_insights']++;
            }

            foreach (['views', 'reach', 'from_followers', 'from_non_followers', 'reactions', 'comments', 'shares', 'clicks'] as $key) {
                $summary[$key] += (int) ($post->metric($key) ?? 0);
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pageSummary(Request $request, string $platform, FacebookService $facebook): ?array
    {
        if ($platform !== 'facebook') {
            return null;
        }

        $page = $request->user()
            ->socialPages()
            ->where('provider', 'facebook')
            ->where('is_connected', true)
            ->orderBy('name')
            ->first();

        if (! $page) {
            return null;
        }

        $data = ['name' => $page->name, 'followers' => null, 'new_follows' => null, 'page_views' => null];

        try {
            $insights = $facebook->getPageSummaryInsights($page->page_id, $page->access_token);

            $data['followers'] = $insights['followers_count'] ?? $insights['page_follows'] ?? null;
            $data['new_follows'] = $insights['page_daily_follows_unique'] ?? $insights['page_daily_follows'] ?? null;
            $data['page_views'] = $insights['page_views_total'] ?? $insights['page_media_view'] ?? null;
        } catch (Throwable $e) {
            report($e);
        }

        return $data;
    }

    private function refreshPostInsights(Post $post, FacebookService $facebook): bool
    {
        if ($post->status !== 'published' || $post->socialPage?->provider !== 'facebook') {
            return false;
        }

        foreach ($this->objectIdCandidates($post) as $objectId) {
            try {
                $insights = $facebook->getPagePostInsights($objectId, $post->socialPage->access_token);
            } catch (Throwable) {
                continue;
            }

            if ($insights !== []) {
                $post->update([
                    'insights' => $insights,
                    'insights_fetched_at' => now(),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function objectIdCandidates(Post $post): array
    {
        $candidates = [];

        if ($post->facebook_post_id) {
            $candidates[] = str_contains($post->facebook_post_id, '_')
                ? $post->facebook_post_id
                : $post->socialPage->page_id.'_'.$post->facebook_post_id;
        }

        if ($post->facebook_video_id) {
            $candidates[] = $post->socialPage->page_id.'_'.$post->facebook_video_id;
            $candidates[] = $post->facebook_video_id;
        }

        return array_values(array_unique($candidates));
    }
}
