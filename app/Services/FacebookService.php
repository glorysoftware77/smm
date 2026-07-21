<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $response = $this->graphGet('/me', $accessToken, [
            'fields' => 'id,name',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch Facebook profile: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @return array{pages: array<int, array>, page_ids: array<int, string>, errors: array<int, string>}
     */
    public function resolveUserPages(string $accessToken): array
    {
        $errors = [];

        $response = $this->graphGet('/me/accounts', $accessToken, [
            'fields' => 'id,name,access_token,category,picture',
            'limit' => 100,
        ]);

        if ($response->successful()) {
            $pages = array_values(array_filter(
                $response->json('data', []),
                fn (array $page) => ! empty($page['id']) && ! empty($page['access_token'])
            ));

            if (count($pages) > 0) {
                return [
                    'pages' => $pages,
                    'page_ids' => array_column($pages, 'id'),
                    'errors' => [],
                ];
            }
        } else {
            $errors[] = 'me/accounts: '.$response->body();
        }

        $pageIds = $this->getGrantedPageIds($accessToken);
        $pages = [];

        foreach ($pageIds as $pageId) {
            try {
                $pages[] = $this->getPage($pageId, $accessToken);
            } catch (RuntimeException $e) {
                $errors[] = $pageId.': '.$e->getMessage();
                Log::warning('Facebook page fetch failed', [
                    'page_id' => $pageId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [
            'pages' => $pages,
            'page_ids' => $pageIds,
            'errors' => $errors,
        ];
    }

    public function getUserPages(string $accessToken): array
    {
        return $this->resolveUserPages($accessToken)['pages'];
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

    public function getPage(string $pageId, string $accessToken): array
    {
        $response = $this->graphGet('/'.$pageId, $accessToken, [
            'fields' => 'id,name,access_token,category,picture',
        ]);

        if (! $response->successful()) {
            // Retry with minimal fields — picture can break some apps.
            $response = $this->graphGet('/'.$pageId, $accessToken, [
                'fields' => 'id,name,access_token,category',
            ]);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Graph error: '.$response->body());
        }

        $page = $response->json();

        if (empty($page['id'])) {
            throw new RuntimeException('Page response missing id: '.$response->body());
        }

        if (empty($page['access_token'])) {
            $tokenResponse = $this->graphGet('/'.$pageId, $accessToken, [
                'fields' => 'access_token',
            ]);

            if ($tokenResponse->successful() && $tokenResponse->json('access_token')) {
                $page['access_token'] = $tokenResponse->json('access_token');
            }
        }

        if (empty($page['access_token'])) {
            throw new RuntimeException('No page access_token returned. Body: '.$response->body());
        }

        if (isset($page['picture']) && ! isset($page['picture']['data']['url']) && isset($page['picture']['url'])) {
            $page['picture'] = ['data' => ['url' => $page['picture']['url']]];
        }

        return $page;
    }

    public function generateState(): string
    {
        return Str::random(40);
    }

    private function graphGet(string $path, string $accessToken, array $query = []): \Illuminate\Http\Client\Response
    {
        return Http::get($this->graphUrl($path), array_merge($query, [
            'access_token' => $accessToken,
            'appsecret_proof' => $this->appSecretProof($accessToken),
        ]));
    }

    private function appSecretProof(string $accessToken): string
    {
        return hash_hmac('sha256', $accessToken, $this->appSecret());
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
