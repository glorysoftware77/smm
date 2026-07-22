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
            ->where('provider', 'facebook')
            ->where('is_connected', true)
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
            'message' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,avi', 'max:102400'],
        ]);

        if ($request->hasFile('image') && $request->hasFile('video')) {
            return back()->withInput()->with('error', 'Choose either an image or a video, not both.');
        }

        if (! $request->filled('message') && ! $request->hasFile('image') && ! $request->hasFile('video')) {
            return back()->withInput()->with('error', 'Add text, an image, or a video before publishing.');
        }

        $page = SocialPage::query()
            ->where('id', $validated['social_page_id'])
            ->where('user_id', $request->user()->id)
            ->where('is_connected', true)
            ->firstOrFail();

        $mediaType = 'none';
        $mediaPath = null;

        if ($request->hasFile('image')) {
            $mediaType = 'image';
            $mediaPath = $request->file('image')->store('posts/'.$request->user()->id, 'local');
        } elseif ($request->hasFile('video')) {
            $mediaType = 'video';
            $mediaPath = $request->file('video')->store('posts/'.$request->user()->id, 'local');
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
            $result = match ($mediaType) {
                'image' => $facebook->publishPhotoPost(
                    $page->page_id,
                    $page->access_token,
                    Storage::disk('local')->path($mediaPath),
                    basename($mediaPath),
                    $validated['message'] ?? null
                ),
                'video' => $facebook->publishVideoPost(
                    $page->page_id,
                    $page->access_token,
                    Storage::disk('local')->path($mediaPath),
                    basename($mediaPath),
                    $validated['message'] ?? null
                ),
                default => $facebook->publishTextPost(
                    $page->page_id,
                    $page->access_token,
                    (string) $validated['message']
                ),
            };

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
