<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class InstagramService
{
    private const GRAPH_VERSION = 'v21.0';

    public function authorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'state' => $state,
            'force_reauth' => 'true',
            'scope' => implode(',', [
                'instagram_business_basic',
                'instagram_business_content_publish',
                'instagram_business_manage_comments',
            ]),
        ]);

        return 'https://www.instagram.com/oauth/authorize?'.$params;
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Failed to exchange Instagram code: '.$response->body());
        }

        return $response->json();
    }

    public function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get('https://graph.instagram.com/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->clientSecret(),
            'access_token' => $shortLivedToken,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Failed to get long-lived Instagram token: '.$response->body());
        }

        return $response->json();
    }

    public function getProfile(string $accessToken): array
    {
        $response = Http::get($this->graphUrl('/me'), [
            'fields' => 'user_id,username,name,account_type,profile_picture_url',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch Instagram profile: '.$response->body());
        }

        $profile = $response->json();

        if (empty($profile['user_id']) && empty($profile['id'])) {
            throw new RuntimeException('Instagram profile missing user id: '.$response->body());
        }

        return $profile;
    }

    public function publishImage(string $igUserId, string $accessToken, string $imageUrl, ?string $caption = null): array
    {
        $payload = [
            'image_url' => $imageUrl,
            'access_token' => $accessToken,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        $container = Http::asForm()->post($this->graphUrl('/'.$igUserId.'/media'), $payload);

        if (! $container->successful() || ! $container->json('id')) {
            throw new RuntimeException('Failed to create Instagram image container: '.$container->body());
        }

        return $this->publishContainer($igUserId, $accessToken, $container->json('id'));
    }

    public function publishReel(string $igUserId, string $accessToken, string $videoUrl, ?string $caption = null): array
    {
        $payload = [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'access_token' => $accessToken,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        $container = Http::asForm()->post($this->graphUrl('/'.$igUserId.'/media'), $payload);

        if (! $container->successful() || ! $container->json('id')) {
            throw new RuntimeException('Failed to create Instagram reel container: '.$container->body());
        }

        $creationId = $container->json('id');
        $this->waitForContainer($creationId, $accessToken);

        return $this->publishContainer($igUserId, $accessToken, $creationId);
    }

    public function generateState(): string
    {
        return Str::random(40);
    }

    private function waitForContainer(string $creationId, string $accessToken, int $attempts = 30): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            $status = Http::get($this->graphUrl('/'.$creationId), [
                'fields' => 'status_code',
                'access_token' => $accessToken,
            ]);

            $code = $status->json('status_code');

            if ($code === 'FINISHED') {
                return;
            }

            if (in_array($code, ['ERROR', 'EXPIRED'], true)) {
                throw new RuntimeException('Instagram media processing failed: '.$status->body());
            }

            sleep(2);
        }

        throw new RuntimeException('Timed out waiting for Instagram media processing.');
    }

    private function publishContainer(string $igUserId, string $accessToken, string $creationId): array
    {
        $response = Http::asForm()->post($this->graphUrl('/'.$igUserId.'/media_publish'), [
            'creation_id' => $creationId,
            'access_token' => $accessToken,
        ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Failed to publish Instagram media: '.$response->body());
        }

        return $response->json();
    }

    private function graphUrl(string $path): string
    {
        return 'https://graph.instagram.com/'.self::GRAPH_VERSION.$path;
    }

    private function clientId(): string
    {
        $id = config('services.instagram.client_id');

        if (! $id) {
            throw new RuntimeException('INSTAGRAM_CLIENT_ID is not configured.');
        }

        return $id;
    }

    private function clientSecret(): string
    {
        $secret = config('services.instagram.client_secret');

        if (! $secret) {
            throw new RuntimeException('INSTAGRAM_CLIENT_SECRET is not configured.');
        }

        return $secret;
    }

    private function redirectUri(): string
    {
        return config('services.instagram.redirect');
    }
}
