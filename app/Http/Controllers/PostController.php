<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SocialPage;
use App\Services\FacebookService;
use App\Services\InstagramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class PostController extends Controller
{
    public function create(Request $request): View
    {
        $pages = $request->user()
            ->socialPages()
            ->whereIn('provider', ['facebook', 'instagram'])
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

    public function store(Request $request, FacebookService $facebook, InstagramService $instagram): RedirectResponse
    {
        $validated = $request->validate([
            'social_page_id' => ['required', 'exists:social_pages,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2200'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,avi', 'max:102400'],
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
            $postFormat = 'reel';
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
            if ($page->provider === 'instagram') {
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

        $label = $postFormat === 'reel' ? 'Reel' : 'Post';

        return redirect()
            ->route('posts.create')
            ->with('success', $label.' published to '.$page->name.'.');
    }

    public function refreshInsights(Request $request, Post $post, FacebookService $facebook): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);

        if ($post->socialPage?->provider !== 'facebook') {
            return redirect()
                ->route('posts.create')
                ->with('error', 'Insights refresh is currently available for Facebook posts only.');
        }

        if ($post->status !== 'published') {
            return redirect()
                ->route('posts.create')
                ->with('error', 'Only published posts can fetch insights.');
        }

        $objectId = $post->facebook_post_id ?: $post->facebook_video_id;

        if (! $objectId) {
            return redirect()
                ->route('posts.create')
                ->with('error', 'No Facebook post/video ID stored for this item.');
        }

        // Page post IDs are often pageId_postId.
        if ($post->facebook_post_id && ! str_contains($post->facebook_post_id, '_')) {
            $objectId = $post->socialPage->page_id.'_'.$post->facebook_post_id;
        }

        try {
            $insights = $facebook->getPagePostInsights($objectId, $post->socialPage->access_token);

            // If post insights empty, try video id.
            if ($insights === [] && $post->facebook_video_id) {
                $insights = $facebook->getPagePostInsights($post->facebook_video_id, $post->socialPage->access_token);
            }

            $post->update([
                'insights' => $insights,
                'insights_fetched_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('posts.create')
                ->with('error', 'Insights failed: '.$e->getMessage());
        }

        return redirect()
            ->route('posts.create')
            ->with('success', 'Insights updated for your Facebook post.');
    }
}
