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
    public function index(Request $request, FacebookService $facebook): View
    {
        $platform = $request->string('platform', 'facebook')->toString();

        if (! in_array($platform, ['facebook', 'instagram'], true)) {
            $platform = 'facebook';
        }

        $posts = $request->user()
            ->posts()
            ->with('socialPage')
            ->where('status', 'published')
            ->whereHas('socialPage', fn ($q) => $q->where('provider', $platform))
            ->latest()
            ->limit(48)
            ->get();

        $summary = [
            'views' => 0,
            'reach' => 0,
            'from_followers' => 0,
            'from_non_followers' => 0,
            'clicks' => 0,
            'posts_with_insights' => 0,
            'posts_total' => $posts->count(),
            'followers' => null,
            'follows_today' => null,
            'page_views' => null,
        ];

        foreach ($posts as $post) {
            $views = $this->numericInsight($post, ['post_media_view', 'post_video_views', 'total_video_views']);
            $reach = $this->numericInsight($post, ['post_total_media_view_unique', 'post_video_views_unique', 'total_video_views_unique']);
            $fromFollowers = $this->numericInsight($post, ['views_from_followers']);
            $fromNonFollowers = $this->numericInsight($post, ['views_from_non_followers']);
            $clicks = $this->numericInsight($post, ['post_clicks']);

            if ($views !== null || $reach !== null || $fromFollowers !== null || $fromNonFollowers !== null) {
                $summary['posts_with_insights']++;
            }

            $summary['views'] += (int) ($views ?? 0);
            $summary['reach'] += (int) ($reach ?? 0);
            $summary['from_followers'] += (int) ($fromFollowers ?? 0);
            $summary['from_non_followers'] += (int) ($fromNonFollowers ?? 0);
            $summary['clicks'] += (int) ($clicks ?? 0);
        }

        if ($platform === 'facebook') {
            $page = $request->user()
                ->socialPages()
                ->where('provider', 'facebook')
                ->where('is_connected', true)
                ->orderBy('name')
                ->first();

            if ($page) {
                try {
                    $pageSummary = $facebook->getPageSummaryInsights($page->page_id, $page->access_token);
                    $summary['followers'] = $pageSummary['page_follows'] ?? $pageSummary['followers_count'] ?? null;
                    $summary['follows_today'] = $pageSummary['page_daily_follows'] ?? $pageSummary['page_follows_unique'] ?? null;
                    $summary['page_views'] = $pageSummary['page_media_view'] ?? $pageSummary['page_views_total'] ?? null;
                    $summary['page_name'] = $page->name;
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return view('insights.index', [
            'platform' => $platform,
            'posts' => $posts,
            'summary' => $summary,
        ]);
    }

    public function refreshAll(Request $request, FacebookService $facebook): RedirectResponse
    {
        $platform = $request->string('platform', 'facebook')->toString();

        if ($platform !== 'facebook') {
            return redirect()
                ->route('insights.index', ['platform' => $platform])
                ->with('error', 'Bulk insights refresh is available for Facebook only right now.');
        }

        $posts = $request->user()
            ->posts()
            ->with('socialPage')
            ->where('status', 'published')
            ->whereHas('socialPage', fn ($q) => $q->where('provider', 'facebook'))
            ->latest()
            ->limit(30)
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($posts as $post) {
            try {
                if ($this->refreshPostInsights($post, $facebook)) {
                    $updated++;
                }
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return redirect()
            ->route('insights.index', ['platform' => 'facebook'])
            ->with('success', "Insights refreshed for {$updated} post(s)".($failed ? ", {$failed} failed/not ready yet." : '.'));
    }

    public function refreshPost(Request $request, Post $post, FacebookService $facebook): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        $platform = $post->socialPage?->provider ?? 'facebook';

        if ($platform !== 'facebook') {
            return redirect()
                ->route('insights.index', ['platform' => $platform])
                ->with('error', 'Insights refresh is currently available for Facebook posts only.');
        }

        try {
            $ok = $this->refreshPostInsights($post, $facebook);

            return redirect()
                ->route('insights.index', ['platform' => 'facebook'])
                ->with($ok ? 'success' : 'error', $ok
                    ? 'Insights updated for this post.'
                    : 'Insights not available yet for this post.');
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('insights.index', ['platform' => 'facebook'])
                ->with('error', 'Insights failed: '.$e->getMessage());
        }
    }

    private function refreshPostInsights(Post $post, FacebookService $facebook): bool
    {
        if ($post->status !== 'published' || $post->socialPage?->provider !== 'facebook') {
            return false;
        }

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

        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $objectId) {
            try {
                $insights = $facebook->getPagePostInsights($objectId, $post->socialPage->access_token);

                if ($insights !== []) {
                    $post->update([
                        'insights' => $insights,
                        'insights_fetched_at' => now(),
                    ]);

                    return true;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function numericInsight(Post $post, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $post->insightValue($key);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }
}
