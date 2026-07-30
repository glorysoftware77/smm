@php
    $pagesPayload = $pages->map(fn ($page) => [
        'id' => $page->id,
        'provider' => $page->provider,
        'name' => $page->name,
        'label' => match ($page->provider) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            default => ucfirst($page->provider),
        },
    ])->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Post') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($pages->isEmpty())
                        <p class="text-sm text-gray-600">
                            Connect Facebook, Instagram, YouTube, or TikTok first from the
                            <a href="{{ route('dashboard') }}" class="text-indigo-600 underline">Dashboard</a>.
                        </p>
                    @else
                        <div
                            x-data="multiPublish({
                                pages: {{ Js::from($pagesPayload) }},
                                publishUrl: {{ Js::from(route('posts.store')) }},
                                csrf: {{ Js::from(csrf_token()) }},
                            })"
                            class="space-y-6"
                        >
                            <form @submit.prevent="publishAll" enctype="multipart/form-data" class="space-y-6">
                                <div>
                                    <x-input-label :value="__('Publish to')" />
                                    <div class="mt-2 space-y-2 rounded-md border border-gray-200 p-3">
                                        <template x-for="page in pages" :key="page.id">
                                            <label class="flex items-center gap-3 text-sm text-gray-800">
                                                <input type="checkbox"
                                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                       :value="page.id"
                                                       x-model="selected">
                                                <span>
                                                    <span class="font-medium" x-text="page.label"></span>
                                                    <span class="text-gray-500"> — <span x-text="page.name"></span></span>
                                                </span>
                                            </label>
                                        </template>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Select one or more. FB videos → Reels. IG needs image/video. YouTube & TikTok need video.
                                    </p>
                                    <p x-show="error && !statuses.length" class="mt-2 text-sm text-red-600" x-text="error"></p>
                                </div>

                                <div>
                                    <x-input-label for="title" :value="__('Title (YouTube / TikTok / FB Reels)')" />
                                    <x-text-input id="title" name="title" class="mt-1 block w-full" x-model="title" />
                                </div>

                                <div>
                                    <x-input-label for="message" :value="__('Message / Caption / Description')" />
                                    <textarea id="message" name="message" rows="5" x-model="message"
                                              class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                              placeholder="Write your post..."></textarea>
                                </div>

                                <div>
                                    <x-input-label for="image" :value="__('Image (optional)')" />
                                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp"
                                           class="mt-1 block w-full text-sm text-gray-700"
                                           @change="imageFile = $event.target.files[0] || null">
                                    <p class="mt-1 text-xs text-gray-500">JPG, PNG, GIF, WebP — max 10MB (not used for YouTube / TikTok)</p>
                                </div>

                                <div>
                                    <x-input-label for="video" :value="__('Video (optional)')" />
                                    <input id="video" name="video" type="file" accept="video/mp4,video/quicktime,video/x-msvideo"
                                           class="mt-1 block w-full text-sm text-gray-700"
                                           @change="videoFile = $event.target.files[0] || null">
                                    <p class="mt-1 text-xs text-gray-500">MP4 / MOV — max 100MB. Shorts tip: 9:16, under 60s.</p>
                                </div>

                                <div class="rounded-md border border-gray-200 p-4 space-y-3"
                                     x-show="selectedProviders.includes('youtube')" x-cloak>
                                    <div class="text-sm font-medium text-gray-800">YouTube options</div>
                                    <div>
                                        <x-input-label for="youtube_privacy" :value="__('Privacy')" />
                                        <select id="youtube_privacy" x-model="youtubePrivacy"
                                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                            <option value="private">Private</option>
                                            <option value="unlisted">Unlisted</option>
                                            <option value="public">Public</option>
                                        </select>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" x-model="youtubeAsShort"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        Publish as YouTube Short (adds #Shorts to title)
                                    </label>
                                </div>

                                <div class="rounded-md border border-gray-200 p-4 space-y-3"
                                     x-show="selectedProviders.includes('tiktok')" x-cloak>
                                    <div class="text-sm font-medium text-gray-800">TikTok options</div>
                                    <div>
                                        <x-input-label for="tiktok_privacy" :value="__('Privacy')" />
                                        <select id="tiktok_privacy" x-model="tiktokPrivacy"
                                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                            <option value="SELF_ONLY">Private (SELF_ONLY) — required until audit</option>
                                            <option value="PUBLIC_TO_EVERYONE">Public</option>
                                            <option value="MUTUAL_FOLLOW_FRIENDS">Friends</option>
                                            <option value="FOLLOWER_OF_CREATOR">Followers</option>
                                        </select>
                                    </div>
                                    <p class="text-xs text-gray-500">Unaudited apps must use SELF_ONLY. Keep the TikTok account private while testing.</p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <x-primary-button type="submit" x-bind:disabled="publishing">
                                        <span x-show="!publishing">{{ __('Publish now') }}</span>
                                        <span x-show="publishing" x-cloak>Publishing…</span>
                                    </x-primary-button>
                                </div>
                            </form>

                            <div x-show="statuses.length" x-cloak class="rounded-md border border-gray-200 bg-gray-50 p-4 space-y-3">
                                <div class="text-sm font-medium text-gray-900">Publish status</div>
                                <ul class="space-y-2">
                                    <template x-for="item in statuses" :key="item.id">
                                        <li class="flex items-start justify-between gap-3 text-sm">
                                            <div>
                                                <span class="font-medium text-gray-900" x-text="item.label"></span>
                                                <span class="text-gray-500"> — <span x-text="item.name"></span></span>
                                                <p class="text-xs text-red-600 mt-0.5" x-show="item.error" x-text="item.error"></p>
                                            </div>
                                            <span class="whitespace-nowrap text-xs font-semibold uppercase tracking-wide"
                                                  :class="{
                                                      'text-gray-400': item.status === 'waiting',
                                                      'text-amber-600': item.status === 'progress',
                                                      'text-green-700': item.status === 'published',
                                                      'text-red-700': item.status === 'failed',
                                                  }"
                                                  x-text="statusText(item)"></span>
                                        </li>
                                    </template>
                                </ul>
                                <p class="text-sm text-green-700" x-show="done && !hasFailures" x-cloak>All selected platforms finished.</p>
                                <p class="text-sm text-amber-700" x-show="done && hasFailures" x-cloak>Finished with some failures — see above.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function multiPublish({ pages, publishUrl, csrf }) {
                return {
                    pages,
                    selected: pages.map(p => String(p.id)),
                    title: '',
                    message: '',
                    imageFile: null,
                    videoFile: null,
                    youtubePrivacy: 'private',
                    youtubeAsShort: false,
                    tiktokPrivacy: 'SELF_ONLY',
                    publishing: false,
                    done: false,
                    error: '',
                    statuses: [],
                    get selectedProviders() {
                        return this.pages
                            .filter(p => this.selected.includes(String(p.id)))
                            .map(p => p.provider);
                    },
                    get hasFailures() {
                        return this.statuses.some(s => s.status === 'failed');
                    },
                    statusText(item) {
                        if (item.status === 'waiting') return 'Waiting';
                        if (item.status === 'progress') return item.label + ' in progress';
                        if (item.status === 'published') return item.label + ' posted';
                        if (item.status === 'failed') return item.label + ' failed';
                        return item.status;
                    },
                    async publishAll() {
                        this.error = '';
                        this.done = false;

                        const targets = this.pages.filter(p => this.selected.includes(String(p.id)));
                        if (!targets.length) {
                            this.error = 'Select at least one platform.';
                            return;
                        }

                        if (this.imageFile && this.videoFile) {
                            this.error = 'Choose either an image or a video, not both.';
                            return;
                        }

                        if (targets.some(p => p.provider === 'youtube') && !this.videoFile) {
                            this.error = 'YouTube requires a video file.';
                            return;
                        }
                        if (targets.some(p => p.provider === 'tiktok') && !this.videoFile) {
                            this.error = 'TikTok requires a video file.';
                            return;
                        }
                        if (targets.some(p => p.provider === 'instagram') && !this.imageFile && !this.videoFile) {
                            this.error = 'Instagram requires an image or video.';
                            return;
                        }
                        if (targets.some(p => p.provider === 'facebook') && !this.message && !this.imageFile && !this.videoFile) {
                            this.error = 'Facebook needs text, an image, or a video.';
                            return;
                        }

                        this.publishing = true;
                        this.statuses = targets.map(p => ({
                            id: p.id,
                            label: p.label,
                            name: p.name,
                            provider: p.provider,
                            status: 'waiting',
                            error: '',
                        }));

                        let mediaPath = null;
                        let mediaType = 'none';

                        for (const target of targets) {
                            const row = this.statuses.find(s => s.id === target.id);
                            row.status = 'progress';

                            const body = new FormData();
                            body.append('social_page_id', target.id);
                            body.append('title', this.title || '');
                            body.append('message', this.message || '');
                            body.append('youtube_privacy', this.youtubePrivacy);
                            if (this.youtubeAsShort) body.append('youtube_as_short', '1');
                            body.append('tiktok_privacy', this.tiktokPrivacy);

                            if (mediaPath) {
                                body.append('media_path', mediaPath);
                                body.append('media_type', mediaType);
                            } else {
                                if (this.imageFile) body.append('image', this.imageFile);
                                if (this.videoFile) body.append('video', this.videoFile);
                            }

                            try {
                                const response = await fetch(publishUrl, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body,
                                });

                                const data = await response.json().catch(() => ({}));

                                if (data.media_path) {
                                    mediaPath = data.media_path;
                                    mediaType = data.media_type || mediaType;
                                }

                                if (!response.ok || data.success === false) {
                                    row.status = 'failed';
                                    row.error = data.message || 'Publish failed.';
                                    continue;
                                }

                                row.status = 'published';
                            } catch (e) {
                                row.status = 'failed';
                                row.error = e.message || 'Network error.';
                            }
                        }

                        this.publishing = false;
                        this.done = true;
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
