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
                            Connect a Facebook Page first from the
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
                                <p class="mt-1 text-xs text-gray-500">Instagram requires an image or video (caption/hashtags go in Message).</p>
                                <x-input-error :messages="$errors->get('social_page_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="message" :value="__('Message')" />
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
                                <x-input-label for="video" :value="__('Video (optional)')" />
                                <input id="video" name="video" type="file" accept="video/mp4,video/quicktime,video/x-msvideo"
                                       class="mt-1 block w-full text-sm text-gray-700">
                                <p class="mt-1 text-xs text-gray-500">MP4 / MOV — max 100MB. Don’t select image and video together.</p>
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
                                <li class="py-3">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ strtoupper($post->socialPage?->provider ?? '') }}
                                                {{ $post->socialPage?->name ?? 'Account' }}
                                                <span class="ml-2 text-xs uppercase tracking-wide
                                                    @if ($post->status === 'published') text-green-700
                                                    @elseif ($post->status === 'failed') text-red-700
                                                    @else text-gray-500 @endif">
                                                    {{ $post->status }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-600 whitespace-pre-line">
                                                {{ \Illuminate\Support\Str::limit($post->message ?: '['.$post->media_type.']', 160) }}
                                            </p>
                                            @if ($post->facebook_post_id)
                                                <p class="mt-1 text-xs text-gray-400">FB ID: {{ $post->facebook_post_id }}</p>
                                            @endif
                                            @if ($post->error_message)
                                                <p class="mt-1 text-xs text-red-600">{{ \Illuminate\Support\Str::limit($post->error_message, 200) }}</p>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-400 whitespace-nowrap">
                                            {{ $post->created_at?->diffForHumans() }}
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
