<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class YouTubeService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://www.googleapis.com/youtube/v3';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';

    public function authorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/youtube.upload',
                'https://www.googleapis.com/auth/youtube.readonly',
            ]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return self::AUTH_URL.'?'.$params;
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Failed to exchange YouTube code: '.$response->body());
        }

        return $response->json();
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Failed to refresh YouTube token: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @return array{channel_id: string, title: string, thumbnail: ?string}
     */
    public function getMyChannel(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::API_BASE.'/channels', [
            'part' => 'snippet,contentDetails',
            'mine' => 'true',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch YouTube channel: '.$response->body());
        }

        $channel = $response->json('items.0');

        if (! $channel) {
            throw new RuntimeException('No YouTube channel found for this Google account.');
        }

        return [
            'channel_id' => $channel['id'],
            'title' => data_get($channel, 'snippet.title', 'YouTube Channel'),
            'thumbnail' => data_get($channel, 'snippet.thumbnails.default.url'),
        ];
    }

    /**
     * Upload a video via resumable upload. Unaudited apps can only publish private.
     *
     * @return array{id: string, status: array<string, mixed>}
     */
    public function uploadVideo(
        string $accessToken,
        string $filePath,
        string $title,
        ?string $description = null,
        string $privacyStatus = 'private',
        bool $asShort = false
    ): array {
        if (! is_file($filePath)) {
            throw new RuntimeException('YouTube upload file not found.');
        }

        $title = trim($title) !== '' ? $title : 'Untitled video';

        if ($asShort && ! str_contains(strtolower($title), '#shorts')) {
            $title = trim($title).' #Shorts';
        }

        $mime = mime_content_type($filePath) ?: 'video/mp4';
        $size = filesize($filePath);

        $metadata = [
            'snippet' => array_filter([
                'title' => Str::limit($title, 100, ''),
                'description' => $description,
                'categoryId' => '22',
            ], fn ($value) => $value !== null && $value !== ''),
            'status' => [
                'privacyStatus' => in_array($privacyStatus, ['public', 'private', 'unlisted'], true)
                    ? $privacyStatus
                    : 'private',
                'selfDeclaredMadeForKids' => false,
            ],
        ];

        $init = Http::withToken($accessToken)
            ->withHeaders([
                'X-Upload-Content-Length' => (string) $size,
                'X-Upload-Content-Type' => $mime,
                'Content-Type' => 'application/json; charset=UTF-8',
            ])
            ->post(self::UPLOAD_URL.'?uploadType=resumable&part=snippet,status', $metadata);

        if (! $init->successful()) {
            throw new RuntimeException('Failed to start YouTube upload: '.$init->body());
        }

        $uploadUrl = $init->header('Location');

        if (! $uploadUrl) {
            throw new RuntimeException('YouTube did not return an upload URL.');
        }

        $upload = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => $mime,
                'Content-Length' => (string) $size,
            ])
            ->withBody(file_get_contents($filePath), $mime)
            ->timeout(600)
            ->put($uploadUrl);

        if (! $upload->successful() || ! $upload->json('id')) {
            throw new RuntimeException('Failed to upload YouTube video: '.$upload->body());
        }

        return $upload->json();
    }

    public function generateState(): string
    {
        return Str::random(40);
    }

    private function clientId(): string
    {
        $id = config('services.youtube.client_id');

        if (! $id) {
            throw new RuntimeException('YOUTUBE_CLIENT_ID is not configured.');
        }

        return $id;
    }

    private function clientSecret(): string
    {
        $secret = config('services.youtube.client_secret');

        if (! $secret) {
            throw new RuntimeException('YOUTUBE_CLIENT_SECRET is not configured.');
        }

        return $secret;
    }

    private function redirectUri(): string
    {
        return config('services.youtube.redirect');
    }
}
