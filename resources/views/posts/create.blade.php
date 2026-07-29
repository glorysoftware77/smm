<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Post') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($pages->isEmpty())
                        <p class="text-sm text-gray-600">
                            Connect a Facebook Page or Instagram account first from the
                            <a href="{{ route('dashboard') }}" class="text-indigo-600 underline">Dashboard</a>.
                        </p>
                    @else
                        <form method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data" class="space-y-6">
                            @csrf

                            <div>
                                <x-input-label for="social_page_id" :value="__('Publish to')" />
                                <select id="social_page_id" name="social_page_id"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        required>
                                    @foreach ($pages as $page)
                                        <option value="{{ $page->id }}" @selected(old('social_page_id') == $page->id)>
                                            {{ strtoupper($page->provider) }} — {{ $page->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">
                                    Facebook videos publish as <strong>Reels</strong>. Instagram requires image or video.
                                </p>
                                <x-input-error :messages="$errors->get('social_page_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="title" :value="__('Title (Facebook Reels, optional)')" />
                                <x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title')" />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="message" :value="__('Message / Caption')" />
                                <textarea id="message" name="message" rows="5"
                                          class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                          placeholder="Write your post...">{{ old('message') }}</textarea>
                                <x-input-error :messages="$errors->get('message')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="image" :value="__('Image (optional)')" />
                                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp"
                                       class="mt-1 block w-full text-sm text-gray-700">
                                <p class="mt-1 text-xs text-gray-500">JPG, PNG, GIF, WebP — max 10MB</p>
                                <x-input-error :messages="$errors->get('image')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="video" :value="__('Reel video (optional)')" />
                                <input id="video" name="video" type="file" accept="video/mp4,video/quicktime,video/x-msvideo"
                                       class="mt-1 block w-full text-sm text-gray-700">
                                <p class="mt-1 text-xs text-gray-500">
                                    MP4 / MOV — max 100MB. For best reach: 9:16, 3–90 seconds, min 540x960.
                                </p>
                                <x-input-error :messages="$errors->get('video')" class="mt-2" />
                            </div>

                            <div class="flex items-center gap-3">
                                <x-primary-button>
                                    {{ __('Publish now') }}
                                </x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            @if ($recentPosts->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 space-y-4">
                        <h3 class="text-lg font-medium">Recent posts</h3>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($recentPosts as $post)
                                <li class="py-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ strtoupper($post->socialPage?->provider ?? '') }}
                                                {{ $post->socialPage?->name ?? 'Account' }}
                                                @if ($post->post_format === 'reel')
                                                    <span class="ml-1 text-xs text-indigo-600">REEL</span>
                                                @endif
                                                <span class="ml-2 text-xs uppercase tracking-wide
                                                    @if ($post->status === 'published') text-green-700
                                                    @elseif ($post->status === 'failed') text-red-700
                                                    @else text-gray-500 @endif">
                                                    {{ $post->status }}
                                                </span>
                                            </div>
                                            @if ($post->title)
                                                <p class="mt-1 text-sm font-medium text-gray-800">{{ $post->title }}</p>
                                            @endif
                                            <p class="mt-1 text-sm text-gray-600 whitespace-pre-line">
                                                {{ \Illuminate\Support\Str::limit($post->message ?: '['.$post->media_type.']', 160) }}
                                            </p>
                                            @if ($post->facebook_post_id || $post->facebook_video_id)
                                                <p class="mt-1 text-xs text-gray-400">
                                                    @if ($post->facebook_post_id) Post: {{ $post->facebook_post_id }} @endif
                                                    @if ($post->facebook_video_id) · Video: {{ $post->facebook_video_id }} @endif
                                                </p>
                                            @endif
                                            @if ($post->error_message)
                                                <p class="mt-1 text-xs text-red-600">{{ \Illuminate\Support\Str::limit($post->error_message, 200) }}</p>
                                            @endif

                                            @if (!empty($post->insights))
                                                <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-600">
                                                    @foreach ([
                                                        'post_impressions' => 'Impressions',
                                                        'post_impressions_unique' => 'Reach',
                                                        'post_impressions_fan' => 'Follower impressions',
                                                        'post_impressions_organic' => 'Organic impressions',
                                                        'post_video_views' => 'Video views',
                                                        'post_video_views_unique' => 'Unique video views',
                                                        'post_media_view' => 'Media views',
                                                        'total_video_views' => 'Total views',
                                                        'total_video_impressions' => 'Total impressions',
                                                        'total_video_views_unique' => 'Unique views',
                                                    ] as $key => $label)
                                                        @if (!is_null($post->insightValue($key)))
                                                            <span class="inline-flex items-center rounded bg-gray-100 px-2 py-1">
                                                                {{ $label }}: <strong class="ml-1">{{ number_format((float) $post->insightValue($key)) }}</strong>
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                                @if ($post->insights_fetched_at)
                                                    <p class="mt-1 text-xs text-gray-400">Updated {{ $post->insights_fetched_at->diffForHumans() }}</p>
                                                @endif
                                            @endif
                                        </div>
                                        <div class="text-right space-y-2 whitespace-nowrap">
                                            <div class="text-xs text-gray-400">
                                                {{ $post->created_at?->diffForHumans() }}
                                            </div>
                                            @if ($post->status === 'published' && $post->socialPage?->provider === 'facebook')
                                                <form method="POST" action="{{ route('posts.insights.refresh', $post) }}">
                                                    @csrf
                                                    <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800">
                                                        Refresh insights
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <p class="text-xs text-gray-500">
                            Tip: Facebook insights can take time to appear after publishing. Wait a bit, then click Refresh insights.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
