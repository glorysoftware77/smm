<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ZernioService
{
    private const API_BASE = 'https://zernio.com/api/v1';

    public function isConfigured(): bool
    {
        return filled(config('services.zernio.api_key'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAccounts(?string $profileId = null): array
    {
        $query = [];

        if ($profileId) {
            $query['profileId'] = $profileId;
        }

        $response = $this->get('/accounts', $query);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to list Zernio accounts: '.$response->body());
        }

        return $response->json('accounts', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLinkedInAccounts(?string $profileId = null): array
    {
        return $this->listPlatformAccounts('linkedin', $profileId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTikTokAccounts(?string $profileId = null): array
    {
        return $this->listPlatformAccounts('tiktok', $profileId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPlatformAccounts(string $platform, ?string $profileId = null): array
    {
        return array_values(array_filter(
            $this->listAccounts($profileId),
            fn (array $account): bool => ($account['platform'] ?? null) === $platform
                && ($account['isActive'] ?? true)
        ));
    }

    /**
     * @return array{id: string, name: string}
     */
    public function ensureProfile(string $name = 'SMM'): array
    {
        $configuredId = config('services.zernio.profile_id');

        if (filled($configuredId)) {
            return [
                'id' => (string) $configuredId,
                'name' => $name,
            ];
        }

        $response = $this->post('/profiles', ['name' => $name]);

        if (! $response->successful() || ! $response->json('profile._id')) {
            throw new RuntimeException('Failed to create Zernio profile: '.$response->body());
        }

        return [
            'id' => (string) $response->json('profile._id'),
            'name' => (string) ($response->json('profile.name') ?: $name),
        ];
    }

    public function connectUrl(string $platform, string $profileId, string $redirectUrl): string
    {
        $response = $this->get('/connect/'.$platform, [
            'profileId' => $profileId,
            'redirect_url' => $redirectUrl,
        ]);

        if (! $response->successful() || ! $response->json('authUrl')) {
            throw new RuntimeException('Failed to get Zernio connect URL: '.$response->body());
        }

        return (string) $response->json('authUrl');
    }

    /**
     * Upload a local file to Zernio temp storage and return the public URL.
     */
    public function uploadMedia(string $filePath, string $filename, string $contentType): string
    {
        if (! is_readable($filePath)) {
            throw new RuntimeException('Media file is not readable for Zernio upload.');
        }

        $size = filesize($filePath);

        $presign = $this->post('/media/presign', array_filter([
            'filename' => $filename,
            'contentType' => $contentType,
            'size' => $size === false ? null : $size,
        ]));

        if (! $presign->successful() || ! $presign->json('uploadUrl') || ! $presign->json('publicUrl')) {
            throw new RuntimeException('Failed to presign Zernio media upload: '.$presign->body());
        }

        $binary = file_get_contents($filePath);

        if ($binary === false) {
            throw new RuntimeException('Unable to read media file for Zernio upload.');
        }

        $upload = Http::withHeaders([
            'Content-Type' => $contentType,
        ])->withBody($binary, $contentType)
            ->timeout(300)
            ->put((string) $presign->json('uploadUrl'));

        if (! $upload->successful()) {
            throw new RuntimeException('Failed to upload media to Zernio storage: '.$upload->body());
        }

        return (string) $presign->json('publicUrl');
    }

    /**
     * Publish immediately to a connected LinkedIn account via Zernio.
     *
     * @return array{id: ?string, url: ?string}
     */
    public function publishLinkedInPost(
        string $accountId,
        string $content,
        ?string $mediaPath = null,
        ?string $mediaType = null,
        ?string $title = null
    ): array {
        return $this->publishPost('linkedin', $accountId, $content, $mediaPath, $mediaType, $title);
    }

    /**
     * Publish a TikTok video via Zernio.
     *
     * @return array{id: ?string, url: ?string}
     */
    public function publishTikTokPost(
        string $accountId,
        string $content,
        string $videoPath,
        string $privacyLevel = 'PUBLIC_TO_EVERYONE'
    ): array {
        $allowed = [
            'PUBLIC_TO_EVERYONE',
            'MUTUAL_FOLLOW_FRIENDS',
            'FOLLOWER_OF_CREATOR',
            'SELF_ONLY',
        ];

        if (! in_array($privacyLevel, $allowed, true)) {
            $privacyLevel = 'PUBLIC_TO_EVERYONE';
        }

        $privacyLevel = $this->resolveTikTokPrivacyLevel($accountId, $privacyLevel);

        return $this->publishPost(
            'tiktok',
            $accountId,
            $content,
            $videoPath,
            'video',
            null,
            [
                'privacy_level' => $privacyLevel,
                'allow_comment' => true,
                'allow_duet' => true,
                'allow_stitch' => true,
                'content_preview_confirmed' => true,
                'express_consent_given' => true,
            ]
        );
    }

    /**
     * Prefer the requested privacy level when the creator allows it; otherwise first available.
     */
    public function resolveTikTokPrivacyLevel(string $accountId, string $preferred): string
    {
        try {
            $response = $this->get('/accounts/'.$accountId.'/tiktok/creator-info', [
                'mediaType' => 'video',
            ]);

            if (! $response->successful()) {
                return $preferred;
            }

            $levels = collect($response->json('privacyLevels', []))
                ->pluck('value')
                ->filter()
                ->values()
                ->all();

            if ($levels === []) {
                return $preferred;
            }

            if (in_array($preferred, $levels, true)) {
                return $preferred;
            }

            return (string) $levels[0];
        } catch (Throwable) {
            return $preferred;
        }
    }

    /**
     * @param  array<string, mixed>|null  $tiktokSettings
     * @return array{id: ?string, url: ?string}
     */
    public function publishPost(
        string $platform,
        string $accountId,
        string $content,
        ?string $mediaPath = null,
        ?string $mediaType = null,
        ?string $title = null,
        ?array $tiktokSettings = null
    ): array {
        $payload = [
            'content' => $content,
            'publishNow' => true,
            'platforms' => [
                [
                    'platform' => $platform,
                    'accountId' => $accountId,
                ],
            ],
        ];

        if (filled($title)) {
            $payload['title'] = $title;
        }

        if ($mediaPath && in_array($mediaType, ['image', 'video'], true)) {
            $filename = basename($mediaPath);
            $contentType = $this->guessContentType($filename, $mediaType);
            $publicUrl = $this->uploadMedia($mediaPath, $filename, $contentType);

            $payload['mediaItems'] = [
                [
                    'url' => $publicUrl,
                    'type' => $mediaType,
                ],
            ];
        }

        if ($platform === 'tiktok' && $tiktokSettings !== null) {
            $payload['tiktokSettings'] = $tiktokSettings;
        }

        $response = Http::withHeaders($this->headers([
            'Content-Type' => 'application/json',
            'x-request-id' => (string) Str::uuid(),
        ]))->timeout(180)
            ->post(self::API_BASE.'/posts', $payload);

        if ($response->status() === 409) {
            $existingId = $response->json('details.existingPostId')
                ?? $response->json('existingPostId');

            return [
                'id' => $existingId ? (string) $existingId : null,
                'url' => null,
            ];
        }

        if (! in_array($response->status(), [200, 201, 207], true)) {
            throw new RuntimeException('Failed to publish '.$platform.' post via Zernio: '.$response->body());
        }

        $post = $response->json('post') ?? $response->json('existingPost') ?? [];
        $platforms = $post['platforms'] ?? [];
        $target = collect($platforms)->firstWhere('platform', $platform) ?? ($platforms[0] ?? []);

        if (($target['status'] ?? null) === 'failed') {
            throw new RuntimeException(
                'Zernio '.$platform.' publish failed: '.($target['error'] ?? $response->body())
            );
        }

        return [
            'id' => isset($post['_id']) ? (string) $post['_id'] : null,
            'url' => isset($target['platformPostUrl']) ? (string) $target['platformPostUrl'] : null,
        ];
    }

    private function guessContentType(string $filename, string $mediaType): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'webm' => 'video/webm',
            'mpeg', 'mpg' => 'video/mpeg',
            'm4v' => 'video/x-m4v',
            default => $mediaType === 'video' ? 'video/mp4' : 'image/jpeg',
        };
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query = []): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout(60)
            ->get(self::API_BASE.$path, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): Response
    {
        return Http::withHeaders($this->headers([
            'Content-Type' => 'application/json',
        ]))->timeout(120)
            ->post(self::API_BASE.$path, $payload);
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function headers(array $extra = []): array
    {
        $apiKey = config('services.zernio.api_key');

        if (! $apiKey) {
            throw new RuntimeException('ZERNIO_API_KEY is not configured.');
        }

        return array_merge([
            'Authorization' => 'Bearer '.$apiKey,
            'Accept' => 'application/json',
        ], $extra);
    }
}
