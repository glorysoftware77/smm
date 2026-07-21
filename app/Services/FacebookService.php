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

        $pages = $response->json('data', []);

        if (count($pages) > 0) {
            return $pages;
        }

        // Meta granular scopes: selected pages may not appear in /me/accounts.
        return $this->getPagesFromGranularScopes($accessToken);
    }

    public function getPagesFromGranularScopes(string $accessToken): array
    {
        $pageIds = $this->getGrantedPageIds($accessToken);
        $pages = [];

        foreach ($pageIds as $pageId) {
            $page = $this->getPage($pageId, $accessToken);

            if ($page !== null) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    public function getGrantedPageIds(string $accessToken): array
    {
        $response = Http::get($this->graphUrl('/debug_token'), [
            'input_token' => $accessToken,
            'access_token' => $this->appId().'|'.$this->appSecret(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to debug Facebook token: '.$response->body());
        }

        $pageIds = [];

        foreach ($response->json('data.granular_scopes', []) as $scope) {
            foreach ($scope['target_ids'] ?? [] as $targetId) {
                $pageIds[] = (string) $targetId;
            }
        }

        return array_values(array_unique($pageIds));
    }

    public function getPage(string $pageId, string $accessToken): ?array
    {
        $response = Http::get($this->graphUrl('/'.$pageId), [
            'fields' => 'id,name,access_token,category,picture{url}',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $page = $response->json();

        if (empty($page['id']) || empty($page['access_token'])) {
            return null;
        }

        return $page;
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
