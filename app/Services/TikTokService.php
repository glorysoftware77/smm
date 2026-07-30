<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class TikTokService
{
    private const AUTH_URL = 'https://www.tiktok.com/v2/auth/authorize/';

    private const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';

    private const API_BASE = 'https://open.tiktokapis.com';

    public function authorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_key' => $this->clientKey(),
            'response_type' => 'code',
            'scope' => implode(',', [
                'user.info.basic',
                'video.publish',
            ]),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);

        return self::AUTH_URL.'?'.$params;
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_key' => $this->clientKey(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        if (! $response->successful() || ! data_get($response->json(), 'access_token') && ! data_get($response->json(), 'data.access_token')) {
            throw new RuntimeException('Failed to exchange TikTok code: '.$response->body());
        }

        $json = $response->json();

        return $json['data'] ?? $json;
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_key' => $this->clientKey(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to refresh TikTok token: '.$response->body());
        }

        $json = $response->json();

        return $json['data'] ?? $json;
    }

    /**
     * @return array{open_id: string, display_name: string, avatar_url: ?string}
     */
    public function getUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::API_BASE.'/v2/user/info/', [
            'fields' => 'open_id,union_id,avatar_url,display_name',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch TikTok user: '.$response->body());
        }

        $user = data_get($response->json(), 'data.user', []);

        if (empty($user['open_id'])) {
            throw new RuntimeException('TikTok user missing open_id: '.$response->body());
        }

        return [
            'open_id' => (string) $user['open_id'],
            'display_name' => (string) ($user['display_name'] ?? 'TikTok'),
            'avatar_url' => $user['avatar_url'] ?? null,
        ];
    }

    public function resolveAccessToken(SocialPage $page): string
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

        $refreshed = $this->refreshAccessToken($account->refresh_token);
        $accessToken = $refreshed['access_token'];

        $account->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshed['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 86400)),
        ]);

        $page->update(['access_token' => $accessToken]);

        return $accessToken;
    }

    /**
     * Direct-post a local video file. Unaudited apps must use SELF_ONLY.
     *
     * @return array{publish_id: string, status: string}
     */
    public function publishVideo(
        string $accessToken,
        string $filePath,
        ?string $title = null,
        string $privacyLevel = 'SELF_ONLY'
    ): array {
        if (! is_file($filePath)) {
            throw new RuntimeException('TikTok upload file not found.');
        }

        $creator = $this->queryCreatorInfo($accessToken);
        $options = $creator['privacy_level_options'] ?? ['SELF_ONLY'];

        if (! in_array($privacyLevel, $options, true)) {
            $privacyLevel = in_array('SELF_ONLY', $options, true)
                ? 'SELF_ONLY'
                : (string) ($options[0] ?? 'SELF_ONLY');
        }

        $size = filesize($filePath);
        $chunkSize = min($size, 10 * 1024 * 1024);
        $totalChunks = (int) ceil($size / max($chunkSize, 1));

        $init = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->post(self::API_BASE.'/v2/post/publish/video/init/', [
                'post_info' => [
                    'title' => Str::limit(trim((string) $title) !== '' ? $title : 'Untitled', 150, ''),
                    'privacy_level' => $privacyLevel,
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                    'video_cover_timestamp_ms' => 1000,
                ],
                'source_info' => [
                    'source' => 'FILE_UPLOAD',
                    'video_size' => $size,
                    'chunk_size' => $chunkSize,
                    'total_chunk_count' => max($totalChunks, 1),
                ],
            ]);

        if (! $init->successful() || ! data_get($init->json(), 'data.upload_url')) {
            throw new RuntimeException('Failed to init TikTok upload: '.$init->body());
        }

        $uploadUrl = data_get($init->json(), 'data.upload_url');
        $publishId = (string) data_get($init->json(), 'data.publish_id');

        $this->uploadFileChunks($uploadUrl, $filePath, $chunkSize);
        $status = $this->waitForPublish($accessToken, $publishId);

        return [
            'publish_id' => $publishId,
            'status' => $status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function queryCreatorInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->post(self::API_BASE.'/v2/post/publish/creator_info/query/');

        if (! $response->successful()) {
            throw new RuntimeException('Failed to query TikTok creator info: '.$response->body());
        }

        return data_get($response->json(), 'data', []);
    }

    public function generateState(): string
    {
        return Str::random(40);
    }

    private function uploadFileChunks(string $uploadUrl, string $filePath, int $chunkSize): void
    {
        $size = filesize($filePath);
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read TikTok video file.');
        }

        $offset = 0;

        try {
            while ($offset < $size) {
                $length = min($chunkSize, $size - $offset);
                $chunk = fread($handle, $length);

                if ($chunk === false) {
                    throw new RuntimeException('Failed reading TikTok video chunk.');
                }

                $end = $offset + strlen($chunk) - 1;

                $response = Http::withHeaders([
                    'Content-Type' => 'video/mp4',
                    'Content-Length' => (string) strlen($chunk),
                    'Content-Range' => "bytes {$offset}-{$end}/{$size}",
                ])
                    ->withBody($chunk, 'video/mp4')
                    ->timeout(300)
                    ->put($uploadUrl);

                if (! in_array($response->status(), [200, 201, 206], true)) {
                    throw new RuntimeException('TikTok chunk upload failed: '.$response->body());
                }

                $offset = $end + 1;
            }
        } finally {
            fclose($handle);
        }
    }

    private function waitForPublish(string $accessToken, string $publishId, int $attempts = 60): string
    {
        for ($i = 0; $i < $attempts; $i++) {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
                ->post(self::API_BASE.'/v2/post/publish/status/fetch/', [
                    'publish_id' => $publishId,
                ]);

            $status = (string) data_get($response->json(), 'data.status', '');

            if (in_array($status, ['PUBLISH_COMPLETE', 'SEND_TO_USER_INBOX'], true)) {
                return $status;
            }

            if (in_array($status, ['FAILED', 'PUBLISH_FAILED'], true)) {
                Log::warning('TikTok publish failed', ['body' => $response->body()]);
                throw new RuntimeException('TikTok publish failed: '.$response->body());
            }

            sleep(2);
        }

        throw new RuntimeException('Timed out waiting for TikTok publish status.');
    }

    private function clientKey(): string
    {
        $key = config('services.tiktok.client_key');

        if (! $key) {
            throw new RuntimeException('TIKTOK_CLIENT_KEY is not configured.');
        }

        return $key;
    }

    private function clientSecret(): string
    {
        $secret = config('services.tiktok.client_secret');

        if (! $secret) {
            throw new RuntimeException('TIKTOK_CLIENT_SECRET is not configured.');
        }

        return $secret;
    }

    private function redirectUri(): string
    {
        return config('services.tiktok.redirect');
    }
}
