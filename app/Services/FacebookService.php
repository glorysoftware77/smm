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
        $params = [
            'client_id' => $this->appId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
        ];

        $configId = config('services.facebook.config_id');

        if ($configId) {
            // Facebook Login for Business — shows Page picker via configuration.
            $params['config_id'] = $configId;
        } else {
            $params['auth_type'] = 'rerequest';
            $params['scope'] = implode(',', [
                'pages_show_list',
                'pages_manage_posts',
                'pages_read_engagement',
                'pages_manage_metadata',
                'instagram_basic',
                'instagram_content_publish',
                'business_management',
                'public_profile',
            ]);
        }

        return 'https://www.facebook.com/'.self::GRAPH_VERSION.'/dialog/oauth?'.http_build_query($params);
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

    public function publishTextPost(string $pageId, string $pageAccessToken, string $message): array
    {
        $response = Http::asForm()->post($this->graphUrl('/'.$pageId.'/feed'), [
            'message' => $message,
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Failed to publish text post: '.$response->body());
        }

        return $response->json();
    }

    public function publishPhotoPost(string $pageId, string $pageAccessToken, string $filePath, string $filename, ?string $caption = null): array
    {
        $payload = [
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        $response = Http::attach('source', fopen($filePath, 'r'), $filename)
            ->post($this->graphUrl('/'.$pageId.'/photos'), $payload);

        if (! $response->successful() || (! $response->json('id') && ! $response->json('post_id'))) {
            throw new RuntimeException('Failed to publish photo: '.$response->body());
        }

        return $response->json();
    }

    public function publishVideoPost(string $pageId, string $pageAccessToken, string $filePath, string $filename, ?string $description = null): array
    {
        $payload = [
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ];

        if ($description !== null && $description !== '') {
            $payload['description'] = $description;
        }

        $response = Http::timeout(300)
            ->attach('source', fopen($filePath, 'r'), $filename)
            ->post($this->graphUrl('/'.$pageId.'/videos'), $payload);

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Failed to publish video: '.$response->body());
        }

        return $response->json();
    }

    public function publishReel(
        string $pageId,
        string $pageAccessToken,
        string $filePath,
        ?string $description = null,
        ?string $title = null
    ): array {
        if (! is_readable($filePath)) {
            throw new RuntimeException('Reel video file is not readable.');
        }

        $start = Http::asForm()->timeout(60)->post($this->graphUrl('/'.$pageId.'/video_reels'), [
            'upload_phase' => 'start',
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ]);

        if (! $start->successful() || ! $start->json('video_id')) {
            throw new RuntimeException('Failed to start Facebook Reel upload: '.$start->body());
        }

        $videoId = $start->json('video_id');
        $uploadUrl = $start->json('upload_url')
            ?: ('https://rupload.facebook.com/video-upload/'.self::GRAPH_VERSION.'/'.$videoId);
        $fileSize = filesize($filePath);

        $upload = Http::withHeaders([
            'Authorization' => 'OAuth '.$pageAccessToken,
            'offset' => '0',
            'file_size' => (string) $fileSize,
            'Content-Type' => 'application/octet-stream',
        ])->withBody(file_get_contents($filePath), 'application/octet-stream')
            ->timeout(300)
            ->post($uploadUrl);

        if (! $upload->successful() || $upload->json('success') !== true) {
            throw new RuntimeException('Failed to upload Facebook Reel binary: '.$upload->body());
        }

        $this->waitForReelUploadReady($videoId, $pageAccessToken);

        $finishPayload = [
            'upload_phase' => 'finish',
            'video_id' => $videoId,
            'video_state' => 'PUBLISHED',
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ];

        if ($description !== null && $description !== '') {
            $finishPayload['description'] = $description;
        }

        if ($title !== null && $title !== '') {
            $finishPayload['title'] = $title;
        }

        $finish = Http::asForm()->timeout(120)->post($this->graphUrl('/'.$pageId.'/video_reels'), $finishPayload);

        if (! $finish->successful()) {
            throw new RuntimeException('Failed to publish Facebook Reel: '.$finish->body());
        }

        return array_merge($finish->json() ?? [], [
            'id' => $finish->json('post_id') ?? $finish->json('video_id') ?? $videoId,
            'post_id' => $finish->json('post_id') ?? null,
            'video_id' => $videoId,
        ]);
    }

    /**
     * Metrics valid after Meta's Nov 2025 Page Insights deprecations.
     */
    private const POST_INSIGHT_METRICS = [
        'post_media_view',
        'post_total_media_view_unique',
        'post_video_views',
        'post_video_views_unique',
        'post_video_avg_time_watched',
        'post_video_view_time',
        'post_clicks',
    ];

    public function getPagePostInsights(string $objectId, string $pageAccessToken): array
    {
        $insights = $this->fetchInsightMetrics($objectId, $pageAccessToken, self::POST_INSIGHT_METRICS);
        $followerSplit = $this->fetchFollowerBreakdown($objectId, $pageAccessToken);
        $engagement = $this->fetchPostEngagement($objectId, $pageAccessToken);

        $merged = array_merge($insights, $followerSplit, $engagement);

        if ($merged === []) {
            throw new RuntimeException('Facebook returned no insights for this post yet. Try again later.');
        }

        return $merged;
    }

    /**
     * Reactions/comments/shares plus permalink and thumbnail, which stay
     * available even when Insights metrics are still empty.
     *
     * @return array<string, mixed>
     */
    private function fetchPostEngagement(string $objectId, string $pageAccessToken): array
    {
        $fieldSets = [
            'permalink_url,full_picture,created_time,shares,reactions.summary(true).limit(0),comments.summary(true).limit(0)',
            'permalink_url,created_time,likes.summary(true).limit(0),comments.summary(true).limit(0),views',
            'permalink_url,created_time',
        ];

        foreach ($fieldSets as $fields) {
            $response = Http::get($this->graphUrl('/'.$objectId), [
                'fields' => $fields,
                'access_token' => $pageAccessToken,
                'appsecret_proof' => $this->appSecretProof($pageAccessToken),
            ]);

            if (! $response->successful()) {
                continue;
            }

            $json = $response->json() ?? [];

            $collected = array_filter([
                'reactions' => data_get($json, 'reactions.summary.total_count')
                    ?? data_get($json, 'likes.summary.total_count'),
                'comments' => data_get($json, 'comments.summary.total_count'),
                'shares' => data_get($json, 'shares.count'),
                'permalink_url' => $json['permalink_url'] ?? null,
                'thumbnail_url' => $json['full_picture'] ?? null,
                'video_views' => $json['views'] ?? null,
            ], fn ($value) => $value !== null);

            if ($collected !== []) {
                return $collected;
            }
        }

        return [];
    }

    /**
     * Page-level summary metrics (followers / follows / views).
     *
     * @return array<string, mixed>
     */
    public function getPageSummaryInsights(string $pageId, string $pageAccessToken, ?int $since = null, ?int $until = null): array
    {
        $collected = [];

        // Lifetime / current follower count via Page fields.
        $page = Http::get($this->graphUrl('/'.$pageId), [
            'fields' => 'followers_count,fan_count,name',
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ]);

        if ($page->successful()) {
            $collected['followers_count'] = $page->json('followers_count') ?? $page->json('fan_count');
            $collected['page_name'] = $page->json('name');
        }

        $dayMetrics = [
            'page_follows',
            'page_daily_follows',
            'page_daily_follows_unique',
            'page_media_view',
            'page_total_media_view_unique',
            'page_views_total',
            'page_post_engagements',
        ];

        foreach ($dayMetrics as $metric) {
            $query = [
                'metric' => $metric,
                'period' => 'day',
            ];

            if ($since !== null && $until !== null) {
                $query['since'] = $since;
                $query['until'] = $until;
            }

            $response = $this->insightsRequest($pageId, $pageAccessToken, $query);

            if (! $response->successful()) {
                continue;
            }

            foreach ($response->json('data', []) as $row) {
                $name = $row['name'] ?? null;
                $values = $row['values'] ?? [];

                if (! $name || $values === []) {
                    continue;
                }

                $sum = 0;
                $hasNumeric = false;

                foreach ($values as $entry) {
                    $value = $entry['value'] ?? null;

                    if (! is_numeric($value)) {
                        continue;
                    }

                    $sum += (float) $value;
                    $hasNumeric = true;
                }

                if ($hasNumeric) {
                    // For ranged requests, sum daily values; otherwise keep latest day.
                    $collected[$name] = ($since !== null && $until !== null)
                        ? $sum
                        : (float) (end($values)['value'] ?? $sum);
                }
            }
        }

        return $collected;
    }

    /**
     * Requests metrics together, then one-by-one so a single invalid metric
     * does not fail the whole request.
     *
     * @param  array<int, string>  $metrics
     * @return array<string, mixed>
     */
    private function fetchInsightMetrics(string $objectId, string $pageAccessToken, array $metrics): array
    {
        $batch = $this->insightsRequest($objectId, $pageAccessToken, [
            'metric' => implode(',', $metrics),
        ]);

        if ($batch->successful()) {
            return $this->normalizeInsights($batch->json('data', []));
        }

        $collected = [];

        foreach ($metrics as $metric) {
            $response = $this->insightsRequest($objectId, $pageAccessToken, [
                'metric' => $metric,
            ]);

            if (! $response->successful()) {
                Log::info('Skipping unsupported Facebook insight metric', [
                    'metric' => $metric,
                    'object_id' => $objectId,
                ]);

                continue;
            }

            $collected = array_merge($collected, $this->normalizeInsights($response->json('data', [])));
        }

        return $collected;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFollowerBreakdown(string $objectId, string $pageAccessToken): array
    {
        $response = $this->insightsRequest($objectId, $pageAccessToken, [
            'metric' => 'post_media_view',
            'breakdown' => 'is_from_followers',
        ]);

        if (! $response->successful()) {
            return [];
        }

        $result = [];

        foreach ($response->json('data', []) as $row) {
            foreach ($row['values'] ?? [] as $entry) {
                $value = $entry['value'] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                foreach ($value as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $dimension = strtolower((string) (data_get($item, 'dimension_values.0') ?? ''));
                    $count = $item['value'] ?? null;

                    if ($count === null || $dimension === '') {
                        continue;
                    }

                    $isFollower = in_array($dimension, ['true', '1', 'follower', 'followers'], true);
                    $key = $isFollower ? 'views_from_followers' : 'views_from_non_followers';
                    $result[$key] = ($result[$key] ?? 0) + (int) $count;
                }
            }
        }

        return $result;
    }

    private function insightsRequest(string $objectId, string $pageAccessToken, array $query): \Illuminate\Http\Client\Response
    {
        return Http::get($this->graphUrl('/'.$objectId.'/insights'), array_merge($query, [
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ]));
    }

    private function waitForReelUploadReady(string $videoId, string $pageAccessToken, int $attempts = 30): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            $status = Http::get($this->graphUrl('/'.$videoId), [
                'fields' => 'status',
                'access_token' => $pageAccessToken,
                'appsecret_proof' => $this->appSecretProof($pageAccessToken),
            ]);

            $uploading = data_get($status->json(), 'status.uploading_phase.status');
            $error = data_get($status->json(), 'status.processing_phase.error.message')
                ?? data_get($status->json(), 'status.uploading_phase.error.message');

            if ($error) {
                throw new RuntimeException('Facebook Reel processing error: '.$error);
            }

            if (in_array($uploading, ['complete', 'completed'], true)) {
                return;
            }

            sleep(2);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int|float|string|null>
     */
    private function normalizeInsights(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $name = $row['name'] ?? null;
            if (! $name) {
                continue;
            }

            $value = data_get($row, 'values.0.value');

            if (is_array($value) || $value === null) {
                continue;
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    public function getInstagramBusinessAccount(string $pageId, string $pageAccessToken): ?array
    {
        $response = $this->graphGet('/'.$pageId, $pageAccessToken, [
            'fields' => 'instagram_business_account{id,username,name,profile_picture_url}',
        ]);

        if (! $response->successful()) {
            Log::warning('Failed to fetch Instagram business account', [
                'page_id' => $pageId,
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json('instagram_business_account');
    }

    public function publishInstagramImage(string $igUserId, string $pageAccessToken, string $imageUrl, ?string $caption = null): array
    {
        $payload = [
            'image_url' => $imageUrl,
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        $container = Http::asForm()->post($this->graphUrl('/'.$igUserId.'/media'), $payload);

        if (! $container->successful() || ! $container->json('id')) {
            throw new RuntimeException('Failed to create Instagram image container: '.$container->body());
        }

        return $this->publishInstagramContainer($igUserId, $pageAccessToken, $container->json('id'));
    }

    public function publishInstagramReel(string $igUserId, string $pageAccessToken, string $videoUrl, ?string $caption = null): array
    {
        $payload = [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        $container = Http::asForm()->post($this->graphUrl('/'.$igUserId.'/media'), $payload);

        if (! $container->successful() || ! $container->json('id')) {
            throw new RuntimeException('Failed to create Instagram reel container: '.$container->body());
        }

        $creationId = $container->json('id');
        $this->waitForInstagramContainer($creationId, $pageAccessToken);

        return $this->publishInstagramContainer($igUserId, $pageAccessToken, $creationId);
    }

    private function waitForInstagramContainer(string $creationId, string $pageAccessToken, int $attempts = 30): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            $status = $this->graphGet('/'.$creationId, $pageAccessToken, [
                'fields' => 'status_code',
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

    private function publishInstagramContainer(string $igUserId, string $pageAccessToken, string $creationId): array
    {
        $response = Http::asForm()->post($this->graphUrl('/'.$igUserId.'/media_publish'), [
            'creation_id' => $creationId,
            'access_token' => $pageAccessToken,
            'appsecret_proof' => $this->appSecretProof($pageAccessToken),
        ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Failed to publish Instagram media: '.$response->body());
        }

        return $response->json();
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
