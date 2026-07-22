<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SocialPage;
use App\Services\FacebookService;
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
            ->limit(10)
            ->get();

        return view('posts.create', [
            'pages' => $pages,
            'recentPosts' => $recentPosts,
        ]);
    }

    public function store(Request $request, FacebookService $facebook): RedirectResponse
    {
        $validated = $request->validate([
            'social_page_id' => ['required', 'exists:social_pages,id'],
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

        if ($hasImage) {
            $mediaType = 'image';
            $mediaPath = $request->file('image')->store('posts/'.$request->user()->id, 'public');
        } elseif ($hasVideo) {
            $mediaType = 'video';
            $mediaPath = $request->file('video')->store('posts/'.$request->user()->id, 'public');
        }

        $post = Post::query()->create([
            'user_id' => $request->user()->id,
            'social_page_id' => $page->id,
            'message' => $validated['message'] ?? null,
            'media_type' => $mediaType,
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
                    ? $facebook->publishInstagramReel(
                        $page->page_id,
                        $page->access_token,
                        $publicUrl,
                        $validated['message'] ?? null
                    )
                    : $facebook->publishInstagramImage(
                        $page->page_id,
                        $page->access_token,
                        $publicUrl,
                        $validated['message'] ?? null
                    );
            } else {
                $absolutePath = $mediaPath
                    ? Storage::disk('public')->path($mediaPath)
                    : null;

                $result = match ($mediaType) {
                    'image' => $facebook->publishPhotoPost(
                        $page->page_id,
                        $page->access_token,
                        $absolutePath,
                        basename($mediaPath),
                        $validated['message'] ?? null
                    ),
                    'video' => $facebook->publishVideoPost(
                        $page->page_id,
                        $page->access_token,
                        $absolutePath,
                        basename($mediaPath),
                        $validated['message'] ?? null
                    ),
                    default => $facebook->publishTextPost(
                        $page->page_id,
                        $page->access_token,
                        (string) $validated['message']
                    ),
                };
            }

            $facebookPostId = $result['post_id'] ?? $result['id'] ?? null;

            $post->update([
                'status' => 'published',
                'facebook_post_id' => $facebookPostId,
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

        return redirect()
            ->route('posts.create')
            ->with('success', 'Published to '.$page->name.'.');
    }
}
