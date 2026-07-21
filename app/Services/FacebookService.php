<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FacebookService
{
    private const GRAPH_VERSION = 'v21.0';

    public function authorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->appId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => implode(',', [
                'pages_show_list',
                'pages_manage_posts',
                'pages_read_engagement',
                'public_profile',
            ]),
        ]);

        return 'https://www.facebook.com/'.self::GRAPH_VERSION.'/dialog/oauth?'.$params;
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::get($this->graphUrl('/oauth/access_token'), [
            'client_id' => $this->appId(),
            'client_secret' => $this->appSecret(),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Failed to exchange Facebook code for token: '.$response->body());
        }

        return $response->json();
    }

    public function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get($this->graphUrl('/oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId(),
            'client_secret' => $this->appSecret(),
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Failed to get long-lived Facebook token: '.$response->body());
        }

        return $response->json();
    }

    public function getUserProfile(string $accessToken): array
    {
        $response = Http::get($this->graphUrl('/me'), [
            'fields' => 'id,name',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch Facebook profile: '.$response->body());
        }

        return $response->json();
    }

    public function getUserPages(string $accessToken): array
    {
        $response = Http::get($this->graphUrl('/me/accounts'), [
            'fields' => 'id,name,access_token,category,picture{url}',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch Facebook pages: '.$response->body());
        }

        return $response->json('data', []);
    }

    public function generateState(): string
    {
        return Str::random(40);
    }

    private function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.self::GRAPH_VERSION.$path;
    }

    private function appId(): string
    {
        $id = config('services.facebook.client_id');

        if (! $id) {
            throw new RuntimeException('FACEBOOK_CLIENT_ID is not configured.');
        }

        return $id;
    }

    private function appSecret(): string
    {
        $secret = config('services.facebook.client_secret');

        if (! $secret) {
            throw new RuntimeException('FACEBOOK_CLIENT_SECRET is not configured.');
        }

        return $secret;
    }

    private function redirectUri(): string
    {
        return config('services.facebook.redirect');
    }
}
