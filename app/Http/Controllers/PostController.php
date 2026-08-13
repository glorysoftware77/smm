<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\FacebookService;
use App\Services\GeminiService;
use App\Services\InstagramService;
use App\Services\TikTokService;
use App\Services\YouTubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PostController extends Controller
{
    public function create(Request $request): View
    {
        $pages = $request->user()
            ->socialPages()
            ->whereIn('provider', ['facebook', 'instagram', 'youtube', 'tiktok'])
            ->where('is_connected', true)
            ->orderBy('provider')
            ->orderBy('name')
            ->get();

        return view('posts.create', [
            'pages' => $pages,
        ]);
    }

    public function generate(Request $request, GeminiService $gemini): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['in:facebook,instagram,youtube,tiktok'],
        ]);

        $platforms = $validated['platforms'] ?? ['facebook', 'instagram', 'youtube'];

        if ($platforms === []) {
            $platforms = ['facebook', 'instagram', 'youtube'];
        }

        try {
            $copy = $gemini->generatePostCopy($validated['prompt'], $platforms);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'title' => $copy['title'],
            'description' => $copy['description'],
            'hashtags' => $copy['hashtags'],
        ]);
    }

    /**
     * Publish to one selected account. Call repeatedly (AJAX) for multi-platform.
     */
    public function store(
        Request $request,
        FacebookService $facebook,
        InstagramService $instagram,
        YouTubeService $youtube,
        TikTokService $tiktok
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'social_page_id' => ['required', 'exists:social_pages,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,avi', 'max:102400'],
            'media_path' => ['nullable', 'string', 'max:500'],
            'media_type' => ['nullable', 'in:none,image,video'],
            'youtube_privacy' => ['nullable', 'in:private,unlisted,public'],
            'youtube_as_short' => ['nullable', 'boolean'],
            'tiktok_privacy' => ['nullable', 'in:SELF_ONLY,PUBLIC_TO_EVERYONE,MUTUAL_FOLLOW_FRIENDS,FOLLOWER_OF_CREATOR'],
        ]);

        if ($request->hasFile('image') && $request->hasFile('video')) {
            return $this->fail($request, 'Choose either an image or a video, not both.');
        }

        $page = SocialPage::query()
            ->where('id', $validated['social_page_id'])
            ->where('user_id', $request->user()->id)
            ->where('is_connected', true)
            ->firstOrFail();

        [$mediaType, $mediaPath] = $this->resolveMedia($request, $validated);

        $hasMessage = filled($validated['message'] ?? null);

        if ($page->provider === 'instagram' && ! in_array($mediaType, ['image', 'video'], true)) {
            return $this->fail($request, 'Instagram requires an image or video.');
        }

        if (in_array($page->provider, ['youtube', 'tiktok'], true) && $mediaType !== 'video') {
            return $this->fail($request, ucfirst($page->provider).' requires a video file.');
        }

        if ($page->provider === 'facebook' && ! $hasMessage && $mediaType === 'none') {
            return $this->fail($request, 'Add text, an image, or a video before publishing.');
        }

        $postFormat = match (true) {
            $page->provider === 'youtube' && $mediaType === 'video' => $request->boolean('youtube_as_short') ? 'short' : 'video',
            $page->provider === 'tiktok' && $mediaType === 'video' => 'video',
            $mediaType === 'video' => 'reel',
            default => 'standard',
        };

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

        $providerLabel = match ($page->provider) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            default => ucfirst($page->provider),
        };

        try {
            [$remotePostId, $remoteVideoId] = $this->publishToProvider(
                $page,
                $mediaType,
                $mediaPath,
                $validated,
                $request,
                $facebook,
                $instagram,
                $youtube,
                $tiktok
            );

            $post->update([
                'status' => 'published',
                'facebook_post_id' => $remotePostId,
                'facebook_video_id' => $remoteVideoId,
                'published_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            report($e);

            $post->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return $this->fail($request, $providerLabel.' failed: '.$e->getMessage(), [
                'provider' => $page->provider,
                'provider_label' => $providerLabel,
                'page_name' => $page->name,
                'media_path' => $mediaPath,
                'media_type' => $mediaType,
                'status' => 'failed',
            ]);
        }

        $payload = [
            'success' => true,
            'message' => $providerLabel.' posted',
            'provider' => $page->provider,
            'provider_label' => $providerLabel,
            'page_name' => $page->name,
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
            'status' => 'published',
            'post_id' => $post->id,
        ];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('posts.create')
            ->with('success', $payload['message'].' to '.$page->name.'.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: ?string}
     */
    private function resolveMedia(Request $request, array $validated): array
    {
        $userPrefix = 'posts/'.$request->user()->id.'/';

        if ($request->hasFile('image')) {
            return ['image', $request->file('image')->store('posts/'.$request->user()->id, 'public')];
        }

        if ($request->hasFile('video')) {
            return ['video', $request->file('video')->store('posts/'.$request->user()->id, 'public')];
        }

        $existingPath = $validated['media_path'] ?? null;
        $existingType = $validated['media_type'] ?? 'none';

        if ($existingPath) {
            if (! str_starts_with($existingPath, $userPrefix) || ! Storage::disk('public')->exists($existingPath)) {
                abort(422, 'Invalid media path.');
            }

            return [$existingType ?: 'none', $existingPath];
        }

        return ['none', null];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: ?string, 1: ?string}
     */
    private function publishToProvider(
        SocialPage $page,
        string $mediaType,
        ?string $mediaPath,
        array $validated,
        Request $request,
        FacebookService $facebook,
        InstagramService $instagram,
        YouTubeService $youtube,
        TikTokService $tiktok
    ): array {
        if ($page->provider === 'tiktok') {
            $accessToken = $tiktok->resolveAccessToken($page);
            $absolutePath = Storage::disk('public')->path($mediaPath);
            $title = $validated['title'] ?: ($validated['message'] ? Str::limit($validated['message'], 140) : 'Untitled');

            $result = $tiktok->publishVideo(
                $accessToken,
                $absolutePath,
                $title,
                $validated['tiktok_privacy'] ?? 'SELF_ONLY'
            );

            return [$result['publish_id'] ?? null, null];
        }

        if ($page->provider === 'youtube') {
            $accessToken = $this->youtubeAccessToken($page, $youtube);
            $absolutePath = Storage::disk('public')->path($mediaPath);

            $result = $youtube->uploadVideo(
                $accessToken,
                $absolutePath,
                $validated['title'] ?: ($validated['message'] ? Str::limit($validated['message'], 80) : 'Untitled'),
                $validated['message'] ?? null,
                $validated['youtube_privacy'] ?? 'private',
                $request->boolean('youtube_as_short')
            );

            return [$result['id'] ?? null, null];
        }

        if ($page->provider === 'instagram') {
            $publicUrl = Storage::disk('public')->url($mediaPath);
            if (! str_starts_with($publicUrl, 'http')) {
                $publicUrl = rtrim(config('app.url'), '/').$publicUrl;
            }

            $result = $mediaType === 'video'
                ? $instagram->publishReel($page->page_id, $page->access_token, $publicUrl, $validated['message'] ?? null)
                : $instagram->publishImage($page->page_id, $page->access_token, $publicUrl, $validated['message'] ?? null);

            return [$result['id'] ?? null, null];
        }

        $absolutePath = $mediaPath ? Storage::disk('public')->path($mediaPath) : null;

        if ($mediaType === 'video') {
            $result = $facebook->publishReel(
                $page->page_id,
                $page->access_token,
                $absolutePath,
                $validated['message'] ?? null,
                $validated['title'] ?? null
            );

            return [$result['post_id'] ?? $result['id'] ?? null, $result['video_id'] ?? null];
        }

        if ($mediaType === 'image') {
            $result = $facebook->publishPhotoPost(
                $page->page_id,
                $page->access_token,
                $absolutePath,
                basename($mediaPath),
                $validated['message'] ?? null
            );

            return [$result['post_id'] ?? $result['id'] ?? null, null];
        }

        $result = $facebook->publishTextPost(
            $page->page_id,
            $page->access_token,
            (string) $validated['message']
        );

        return [$result['id'] ?? null, null];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function fail(Request $request, string $message, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(array_merge([
                'success' => false,
                'message' => $message,
                'status' => 'failed',
            ], $extra), 422);
        }

        return back()->withInput()->with('error', $message);
    }

    private function youtubeAccessToken(SocialPage $page, YouTubeService $youtube): string
    {
        if (method_exists($youtube, 'resolveAccessToken')) {
            return $youtube->resolveAccessToken($page);
        }

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
