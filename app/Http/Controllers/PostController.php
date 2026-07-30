<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\FacebookService;
use App\Services\InstagramService;
use App\Services\YouTubeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class PostController extends Controller
{
    public function create(Request $request): View
    {
        $pages = $request->user()
            ->socialPages()
            ->whereIn('provider', ['facebook', 'instagram', 'youtube'])
            ->where('is_connected', true)
            ->orderBy('provider')
            ->orderBy('name')
            ->get();

        $recentPosts = $request->user()
            ->posts()
            ->with('socialPage')
            ->latest()
            ->limit(15)
            ->get();

        return view('posts.create', [
            'pages' => $pages,
            'recentPosts' => $recentPosts,
        ]);
    }

    public function store(
        Request $request,
        FacebookService $facebook,
        InstagramService $instagram,
        YouTubeService $youtube
    ): RedirectResponse {
        $validated = $request->validate([
            'social_page_id' => ['required', 'exists:social_pages,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,avi', 'max:102400'],
            'youtube_privacy' => ['nullable', 'in:private,unlisted,public'],
            'youtube_as_short' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('image') && $request->hasFile('video')) {
            return back()->withInput()->with('error', 'Choose either an image or a video, not both.');
        }

        $page = SocialPage::query()
            ->where('id', $validated['social_page_id'])
            ->where('user_id', $request->user()->id)
            ->where('is_connected', true)
            ->firstOrFail();

        $hasImage = $request->hasFile('image');
        $hasVideo = $request->hasFile('video');
        $hasMessage = $request->filled('message');

        if ($page->provider === 'instagram' && ! $hasImage && ! $hasVideo) {
            return back()->withInput()->with('error', 'Instagram requires an image or video. Text-only posts are not supported.');
        }

        if ($page->provider === 'youtube' && ! $hasVideo) {
            return back()->withInput()->with('error', 'YouTube requires a video file.');
        }

        if ($page->provider === 'facebook' && ! $hasMessage && ! $hasImage && ! $hasVideo) {
            return back()->withInput()->with('error', 'Add text, an image, or a video before publishing.');
        }

        $mediaType = 'none';
        $mediaPath = null;
        $postFormat = 'standard';

        if ($hasImage) {
            $mediaType = 'image';
            $mediaPath = $request->file('image')->store('posts/'.$request->user()->id, 'public');
        } elseif ($hasVideo) {
            $mediaType = 'video';
            $postFormat = $page->provider === 'youtube'
                ? ($request->boolean('youtube_as_short') ? 'short' : 'video')
                : 'reel';
            $mediaPath = $request->file('video')->store('posts/'.$request->user()->id, 'public');
        }

        $post = Post::query()->create([
            'user_id' => $request->user()->id,
            'social_page_id' => $page->id,
            'title' => $validated['title'] ?? null,
            'message' => $validated['message'] ?? null,
            'media_type' => $mediaType,
            'post_format' => $postFormat,
            'media_path' => $mediaPath,
            'status' => 'pending',
        ]);

        try {
            if ($page->provider === 'youtube') {
                $accessToken = $this->youtubeAccessToken($page, $youtube);
                $absolutePath = Storage::disk('public')->path($mediaPath);
                $privacy = $validated['youtube_privacy'] ?? 'private';

                $result = $youtube->uploadVideo(
                    $accessToken,
                    $absolutePath,
                    $validated['title'] ?: ($validated['message'] ? \Illuminate\Support\Str::limit($validated['message'], 80) : 'Untitled'),
                    $validated['message'] ?? null,
                    $privacy,
                    $request->boolean('youtube_as_short')
                );

                $facebookPostId = $result['id'] ?? null;
                $facebookVideoId = null;
            } elseif ($page->provider === 'instagram') {
                $publicUrl = Storage::disk('public')->url($mediaPath);
                if (! str_starts_with($publicUrl, 'http')) {
                    $publicUrl = rtrim(config('app.url'), '/').$publicUrl;
                }

                $result = $mediaType === 'video'
                    ? $instagram->publishReel(
                        $page->page_id,
                        $page->access_token,
                        $publicUrl,
                        $validated['message'] ?? null
                    )
                    : $instagram->publishImage(
                        $page->page_id,
                        $page->access_token,
                        $publicUrl,
                        $validated['message'] ?? null
                    );

                $facebookPostId = $result['id'] ?? null;
                $facebookVideoId = null;
            } else {
                $absolutePath = $mediaPath
                    ? Storage::disk('public')->path($mediaPath)
                    : null;

                if ($mediaType === 'video') {
                    $result = $facebook->publishReel(
                        $page->page_id,
                        $page->access_token,
                        $absolutePath,
                        $validated['message'] ?? null,
                        $validated['title'] ?? null
                    );
                    $facebookPostId = $result['post_id'] ?? $result['id'] ?? null;
                    $facebookVideoId = $result['video_id'] ?? null;
                } elseif ($mediaType === 'image') {
                    $result = $facebook->publishPhotoPost(
                        $page->page_id,
                        $page->access_token,
                        $absolutePath,
                        basename($mediaPath),
                        $validated['message'] ?? null
                    );
                    $facebookPostId = $result['post_id'] ?? $result['id'] ?? null;
                    $facebookVideoId = null;
                } else {
                    $result = $facebook->publishTextPost(
                        $page->page_id,
                        $page->access_token,
                        (string) $validated['message']
                    );
                    $facebookPostId = $result['id'] ?? null;
                    $facebookVideoId = null;
                }
            }

            $post->update([
                'status' => 'published',
                'facebook_post_id' => $facebookPostId,
                'facebook_video_id' => $facebookVideoId,
                'published_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            report($e);

            $post->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('posts.create')
                ->with('error', 'Publish failed: '.$e->getMessage());
        }

        $label = match ($postFormat) {
            'short' => 'Short',
            'reel' => 'Reel',
            'video' => 'Video',
            default => 'Post',
        };

        $privacyNote = $page->provider === 'youtube'
            ? ' (privacy: '.($validated['youtube_privacy'] ?? 'private').'; — unaudited apps may stay private)'
            : '';

        return redirect()
            ->route('posts.create')
            ->with('success', $label.' published to '.$page->name.$privacyNote.'.');
    }

    private function youtubeAccessToken(SocialPage $page, YouTubeService $youtube): string
    {
        $account = $page->socialAccount;

        if (! $account instanceof SocialAccount) {
            return $page->access_token;
        }

        $expiresAt = $account->token_expires_at;
        $stillValid = $expiresAt instanceof Carbon && $expiresAt->isAfter(now()->addMinutes(2));

        if ($stillValid || ! $account->refresh_token) {
            return $account->access_token ?: $page->access_token;
        }

        $refreshed = $youtube->refreshAccessToken($account->refresh_token);
        $accessToken = $refreshed['access_token'];

        $account->update([
            'access_token' => $accessToken,
            'token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600)),
        ]);

        $page->update(['access_token' => $accessToken]);

        return $accessToken;
    }

    public function refreshInsights(Request $request, Post $post, FacebookService $facebook): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        if ($post->socialPage?->provider !== 'facebook') {
            return redirect()
                ->route('insights.index', ['platform' => $post->socialPage?->provider ?? 'facebook'])
                ->with('error', 'Insights refresh is currently available for Facebook posts only.');
        }

        if ($post->status !== 'published') {
            return redirect()
                ->route('insights.index')
                ->with('error', 'Only published posts can fetch insights.');
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

        if ($candidates === []) {
            return redirect()
                ->route('insights.index')
                ->with('error', 'No Facebook post/video ID stored for this item.');
        }

        $insights = [];
        $lastError = null;

        foreach ($candidates as $objectId) {
            try {
                $insights = $facebook->getPagePostInsights($objectId, $post->socialPage->access_token);

                if ($insights !== []) {
                    break;
                }
            } catch (Throwable $e) {
                $lastError = $e;
            }
        }

        if ($insights === []) {
            if ($lastError) {
                report($lastError);
            }

            return redirect()
                ->route('insights.index')
                ->with('error', 'Insights not available yet'.($lastError ? ': '.$lastError->getMessage() : '.'));
        }

        $post->update([
            'insights' => $insights,
            'insights_fetched_at' => now(),
        ]);

        return redirect()
            ->route('insights.index')
            ->with('success', 'Insights updated for your Facebook post.');
    }
}
