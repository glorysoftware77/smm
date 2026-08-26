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
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="kicker">Composer</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[#1A1D23] sm:text-4xl">
                    {{ __('Create Post') }}
                </h2>
                <p class="mt-2 max-w-xl text-[15px] leading-relaxed text-[#5C534C]">Write once, publish to the accounts you select.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="btn-ghost shrink-0 self-start sm:self-auto">← Accounts</a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="panel">
                <div class="p-5 sm:p-8 text-[#1A1D23]">
                    @if ($pages->isEmpty())
                        <div class="flex flex-col items-start gap-4 rounded-2xl border border-dashed border-[#D6CBC3] bg-[#FAF8F6] px-5 py-10">
                            <p class="kicker">Get started</p>
                            <p class="max-w-md text-sm leading-relaxed text-[#5C534C]">
                                Connect Facebook, Instagram, YouTube, or TikTok first from the Dashboard before composing a post.
                            </p>
                            <a href="{{ route('dashboard') }}" class="btn-primary">Go to Dashboard</a>
                        </div>
                    @else
                        <div
                            x-data="multiPublish({
                                pages: {{ Js::from($pagesPayload) }},
                                publishUrl: {{ Js::from(route('posts.store')) }},
                                generateUrl: {{ Js::from(route('posts.generate')) }},
                                csrf: {{ Js::from(csrf_token()) }},
                            })"
                            class="space-y-6"
                        >
                            <form @submit.prevent="publishAll" enctype="multipart/form-data" class="space-y-6">
                                {{-- Step 1: Accounts --}}
                                <section class="section-card space-y-4">
                                    <div>
                                        <p class="kicker">Step 1</p>
                                        <h3 class="mt-1 text-lg font-semibold tracking-tight">Publish to</h3>
                                        <p class="mt-1 text-sm text-[#5C534C]">Select one or more accounts. Requirements differ by network.</p>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <template x-for="page in pages" :key="page.id">
                                            <label class="group relative flex cursor-pointer items-start gap-3 overflow-hidden rounded-2xl border px-4 py-4 transition"
                                                   :class="selected.includes(String(page.id))
                                                       ? 'border-glory-500 bg-glory-50/70 shadow-[0_0_0_1px_rgba(107,44,62,0.15)]'
                                                       : 'border-[#E4D9D1] bg-white hover:border-[#C4B8B0] hover:bg-[#FAF8F6]'">
                                                <span class="absolute inset-y-0 left-0 w-1"
                                                      :class="{
                                                          'bg-[#1877F2]': page.provider === 'facebook',
                                                          'bg-gradient-to-b from-[#F58529] via-[#DD2A7B] to-[#8134AF]': page.provider === 'instagram',
                                                          'bg-[#FF0000]': page.provider === 'youtube',
                                                          'bg-[#1A1D23]': page.provider === 'tiktok',
                                                      }"></span>
                                                <input type="checkbox"
                                                       class="mt-1 rounded border-[#C4B8B0] text-glory-500 shadow-sm focus:ring-glory-500"
                                                       :value="page.id"
                                                       x-model="selected">
                                                <span class="min-w-0">
                                                    <span class="flex items-center gap-2">
                                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-[11px] font-bold text-white"
                                                              :class="{
                                                                  'bg-[#1877F2]': page.provider === 'facebook',
                                                                  'bg-gradient-to-br from-[#F58529] via-[#DD2A7B] to-[#8134AF]': page.provider === 'instagram',
                                                                  'bg-[#FF0000]': page.provider === 'youtube',
                                                                  'bg-[#1A1D23]': page.provider === 'tiktok',
                                                              }"
                                                              x-text="page.provider === 'facebook' ? 'f' : (page.provider === 'instagram' ? 'Ig' : (page.provider === 'youtube' ? '▶' : 'TT'))"></span>
                                                        <span class="text-sm font-semibold" x-text="page.label"></span>
                                                    </span>
                                                    <span class="mt-1 block truncate text-xs text-[#8B8680]" x-text="page.name"></span>
                                                </span>
                                            </label>
                                        </template>
                                    </div>

                                    <p class="field-hint !mt-0">
                                        FB videos → Reels. IG needs image/video. YouTube &amp; TikTok need video.
                                    </p>
                                    <p x-show="error && !statuses.length" class="text-sm text-red-600" x-text="error"></p>
                                </section>

                                {{-- Step 2: AI --}}
                                <section class="section-card space-y-4 bg-gradient-to-br from-glory-50/80 via-white to-[#FAF8F6]">
                                    <div>
                                        <p class="kicker">Step 2 · AI assist</p>
                                        <h3 class="mt-1 text-lg font-semibold tracking-tight">Generate with Gemini</h3>
                                        <p class="mt-1 text-sm text-[#5C534C]">
                                            Writes a Reel/YouTube title, caption (keeps emojis), and hashtags.
                                        </p>
                                    </div>
                                    <div>
                                        <x-input-label for="ai_prompt" :value="__('Brief')" />
                                        <textarea id="ai_prompt" rows="3" x-model="prompt"
                                                  class="field-input"
                                                  placeholder="e.g. Summer AC service offer in Chennai, 20% off this week, call to book"></textarea>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="button" class="btn-primary" x-bind:disabled="generating" @click="generateCopy">
                                            <span x-show="!generating">Generate copy</span>
                                            <span x-show="generating" x-cloak>Generating…</span>
                                        </button>
                                        <p class="text-sm text-red-600" x-show="generateError" x-text="generateError"></p>
                                    </div>
                                </section>

                                {{-- Step 3: Copy --}}
                                <section class="section-card space-y-5">
                                    <div>
                                        <p class="kicker">Step 3</p>
                                        <h3 class="mt-1 text-lg font-semibold tracking-tight">Copy</h3>
                                    </div>

                                    <div>
                                        <x-input-label for="title" :value="__('Title (YouTube / Facebook Reels)')" />
                                        <x-text-input id="title" name="title" class="block w-full" x-model="title" maxlength="100" />
                                        <p class="field-hint">YouTube title &amp; Facebook Reel title. Max 100 characters.</p>
                                    </div>

                                    <div>
                                        <x-input-label for="description" :value="__('Caption / description')" />
                                        <textarea id="description" name="description" rows="7" x-model="description"
                                                  class="field-input"
                                                  placeholder="Write your post… emojis and line breaks are kept."></textarea>
                                        <p class="field-hint">Used for Facebook, Instagram, and YouTube.</p>
                                    </div>

                                    <div>
                                        <x-input-label for="hashtags" :value="__('Hashtags')" />
                                        <textarea id="hashtags" name="hashtags" rows="2" x-model="hashtags"
                                                  class="field-input"
                                                  placeholder="#YourBrand #Topic"></textarea>
                                        <p class="field-hint">Appended under the caption for Instagram, Facebook, and YouTube.</p>
                                    </div>
                                </section>

                                {{-- Step 4: Media --}}
                                <section class="section-card space-y-5">
                                    <div>
                                        <p class="kicker">Step 4</p>
                                        <h3 class="mt-1 text-lg font-semibold tracking-tight">Media</h3>
                                        <p class="mt-1 text-sm text-[#5C534C]">Add an image or a video — not both.</p>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <x-input-label for="image" :value="__('Image (optional)')" />
                                            <label class="dropzone mt-2" :class="imageFile ? 'dropzone-active' : ''">
                                                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp"
                                                       class="absolute inset-0 cursor-pointer opacity-0"
                                                       @change="imageFile = $event.target.files[0] || null; if (imageFile) videoFile = null">
                                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-glory-500 shadow-sm ring-1 ring-[#E4D9D1]">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" /></svg>
                                                </span>
                                                <span class="text-sm font-semibold text-[#1A1D23]" x-text="imageFile ? imageFile.name : 'Drop or browse image'"></span>
                                                <span class="text-xs text-[#8B8680]">JPG, PNG, GIF, WebP — max 10MB</span>
                                            </label>
                                        </div>

                                        <div>
                                            <x-input-label for="video" :value="__('Video (optional)')" />
                                            <label class="dropzone mt-2" :class="videoFile ? 'dropzone-active' : ''">
                                                <input id="video" name="video" type="file" accept="video/mp4,video/quicktime,video/x-msvideo"
                                                       class="absolute inset-0 cursor-pointer opacity-0"
                                                       @change="videoFile = $event.target.files[0] || null; if (videoFile) imageFile = null">
                                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-glory-500 shadow-sm ring-1 ring-[#E4D9D1]">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" /></svg>
                                                </span>
                                                <span class="text-sm font-semibold text-[#1A1D23]" x-text="videoFile ? videoFile.name : 'Drop or browse video'"></span>
                                                <span class="text-xs text-[#8B8680]">MP4 / MOV — max 100MB · Shorts: 9:16, under 60s</span>
                                            </label>
                                        </div>
                                    </div>
                                </section>

                                {{-- Platform options --}}
                                <section class="section-card space-y-3"
                                         x-show="selectedProviders.includes('youtube')" x-cloak>
                                    <div>
                                        <p class="kicker">YouTube</p>
                                        <h3 class="mt-1 text-base font-semibold">Publish options</h3>
                                    </div>
                                    <div>
                                        <x-input-label for="youtube_privacy" :value="__('Privacy')" />
                                        <select id="youtube_privacy" x-model="youtubePrivacy" class="field-input">
                                            <option value="private">Private</option>
                                            <option value="unlisted">Unlisted</option>
                                            <option value="public">Public</option>
                                        </select>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-[#1A1D23]">
                                        <input type="checkbox" x-model="youtubeAsShort"
                                               class="rounded border-[#C4B8B0] text-glory-500 shadow-sm focus:ring-glory-500">
                                        Publish as YouTube Short (adds #Shorts to title)
                                    </label>
                                </section>

                                <section class="section-card space-y-3"
                                         x-show="selectedProviders.includes('tiktok')" x-cloak>
                                    <div>
                                        <p class="kicker">TikTok</p>
                                        <h3 class="mt-1 text-base font-semibold">Publish options</h3>
                                    </div>
                                    <div>
                                        <x-input-label for="tiktok_privacy" :value="__('Privacy')" />
                                        <select id="tiktok_privacy" x-model="tiktokPrivacy" class="field-input">
                                            <option value="SELF_ONLY">Private (SELF_ONLY) — required until audit</option>
                                            <option value="PUBLIC_TO_EVERYONE">Public</option>
                                            <option value="MUTUAL_FOLLOW_FRIENDS">Friends</option>
                                            <option value="FOLLOWER_OF_CREATOR">Followers</option>
                                        </select>
                                    </div>
                                    <p class="field-hint !mt-0">Unaudited apps must use SELF_ONLY. Keep the TikTok account private while testing.</p>
                                </section>

                                {{-- Publish bar --}}
                                <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#E4D9D1] bg-white/95 px-5 py-4 shadow-[0_12px_40px_-16px_rgba(107,44,62,0.35)] backdrop-blur">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-[#1A1D23]">
                                            Ready to publish
                                        </p>
                                        <p class="text-xs text-[#8B8680]" x-text="selected.length + ' account' + (selected.length === 1 ? '' : 's') + ' selected'"></p>
                                    </div>
                                    <x-primary-button type="submit" x-bind:disabled="publishing">
                                        <span x-show="!publishing">{{ __('Publish now') }}</span>
                                        <span x-show="publishing" x-cloak>Publishing…</span>
                                    </x-primary-button>
                                </div>
                            </form>

                            <div x-show="statuses.length" x-cloak class="section-card space-y-3 bg-[#FAF8F6]">
                                <div class="text-sm font-semibold text-[#1A1D23]">Publish status</div>
                                <ul class="space-y-2">
                                    <template x-for="item in statuses" :key="item.id">
                                        <li class="flex items-start justify-between gap-3 rounded-xl border border-[#E4D9D1] bg-white px-4 py-3 text-sm">
                                            <div>
                                                <span class="font-semibold text-[#1A1D23]" x-text="item.label"></span>
                                                <span class="text-[#8B8680]"> — <span x-text="item.name"></span></span>
                                                <p class="text-xs text-red-600 mt-0.5" x-show="item.error" x-text="item.error"></p>
                                            </div>
                                            <span class="whitespace-nowrap text-xs font-semibold uppercase tracking-wide"
                                                  :class="{
                                                      'text-[#8B8680]': item.status === 'waiting',
                                                      'text-amber-600': item.status === 'progress',
                                                      'text-emerald-700': item.status === 'published',
                                                      'text-red-700': item.status === 'failed',
                                                  }"
                                                  x-text="statusText(item)"></span>
                                        </li>
                                    </template>
                                </ul>
                                <p class="text-sm text-emerald-700" x-show="done && !hasFailures" x-cloak>All selected platforms finished.</p>
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
            function multiPublish({ pages, publishUrl, generateUrl, csrf }) {
                return {
                    pages,
                    selected: pages.map(p => String(p.id)),
                    prompt: '',
                    title: '',
                    description: '',
                    hashtags: '',
                    imageFile: null,
                    videoFile: null,
                    youtubePrivacy: 'private',
                    youtubeAsShort: false,
                    tiktokPrivacy: 'SELF_ONLY',
                    publishing: false,
                    generating: false,
                    generateError: '',
                    done: false,
                    error: '',
                    statuses: [],
                    get selectedProviders() {
                        return this.pages
                            .filter(p => this.selected.includes(String(p.id)))
                            .map(p => p.provider);
                    },
                    get composedMessage() {
                        const description = (this.description || '').trim();
                        const hashtags = (this.hashtags || '').trim();
                        if (description && hashtags) return description + '\n\n' + hashtags;
                        return description || hashtags;
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
                    async generateCopy() {
                        this.generateError = '';
                        if (!this.prompt.trim()) {
                            this.generateError = 'Enter a prompt first.';
                            return;
                        }

                        this.generating = true;
                        try {
                            const response = await fetch(generateUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    prompt: this.prompt,
                                    platforms: this.selectedProviders.length
                                        ? [...new Set(this.selectedProviders)]
                                        : ['facebook', 'instagram', 'youtube'],
                                }),
                            });
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok || data.success === false) {
                                this.generateError = data.message || 'Could not generate copy.';
                                return;
                            }
                            this.title = data.title || '';
                            this.description = data.description || '';
                            this.hashtags = data.hashtags || '';
                        } catch (e) {
                            this.generateError = e.message || 'Network error.';
                        } finally {
                            this.generating = false;
                        }
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
                        if (targets.some(p => p.provider === 'facebook') && !this.composedMessage && !this.imageFile && !this.videoFile) {
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
                            body.append('message', this.composedMessage || '');
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
