<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class LinkedInService
{
    private const AUTH_URL = 'https://www.linkedin.com/oauth/v2/authorization';

    private const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    private const API_BASE = 'https://api.linkedin.com/rest';

    /**
     * Community Management scopes for Page connect + publish.
     * Use the Communication / Community Management app credentials
     * (CM must be the only product on that LinkedIn app).
     */
    private const SCOPES = [
        'w_organization_social',
        'r_organization_social',
        'rw_organization_admin',
    ];

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => implode(' ', self::SCOPES),
        ]);
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Failed to exchange LinkedIn code: '.$response->body());
        }

        return $response->json();
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Failed to refresh LinkedIn token: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @return array{id: string, name: ?string, email: ?string, picture: ?string}
     */
    public function getMemberProfile(string $accessToken): array
    {
        // Community Management apps typically lack OpenID Connect; derive member id from ACLs.
        $acls = $this->getOrganizationAcls($accessToken);

        foreach ($acls as $acl) {
            $assignee = $acl['roleAssignee'] ?? null;

            if (is_string($assignee) && preg_match('/urn:li:person:(.+)$/', $assignee, $m)) {
                return [
                    'id' => $m[1],
                    'name' => 'LinkedIn Member',
                    'email' => null,
                    'picture' => null,
                ];
            }
        }

        throw new RuntimeException(
            'No LinkedIn Company Pages found for this member. You must be an ADMINISTRATOR, CONTENT_ADMIN, or DIRECT_SPONSORED_CONTENT_POSTER on at least one Page.'
        );
    }

    /**
     * Pages the member can post to (admin / content roles).
     *
     * @return array<int, array{id: string, name: string, category: ?string, picture_url: ?string, role: ?string}>
     */
    public function resolveOrganizationPages(string $accessToken): array
    {
        $acls = $this->getOrganizationAcls($accessToken);
        $pages = [];
        $seen = [];

        $postingRoles = [
            'ADMINISTRATOR',
            'DIRECT_SPONSORED_CONTENT_POSTER',
            'CONTENT_ADMIN',
        ];

        foreach ($acls as $acl) {
            $role = $acl['role'] ?? null;
            $state = $acl['state'] ?? null;
            $orgUrn = $acl['organization'] ?? null;

            if ($state !== 'APPROVED' || ! in_array($role, $postingRoles, true) || ! is_string($orgUrn)) {
                continue;
            }

            if (! preg_match('/urn:li:organization:(\d+)$/', $orgUrn, $m)) {
                continue;
            }

            $orgId = $m[1];

            if (isset($seen[$orgId])) {
                continue;
            }

            $seen[$orgId] = true;

            try {
                $org = $this->getOrganization($orgId, $accessToken);
                $pages[] = [
                    'id' => $orgId,
                    'name' => $org['name'] ?? ('LinkedIn Page '.$orgId),
                    'category' => $org['category'] ?? 'LinkedIn Page',
                    'picture_url' => $org['picture_url'] ?? null,
                    'role' => $role,
                ];
            } catch (RuntimeException $e) {
                Log::warning('LinkedIn organization fetch failed', [
                    'organization_id' => $orgId,
                    'message' => $e->getMessage(),
                ]);

                $pages[] = [
                    'id' => $orgId,
                    'name' => 'LinkedIn Page '.$orgId,
                    'category' => 'LinkedIn Page',
                    'picture_url' => null,
                    'role' => $role,
                ];
            }
        }

        return $pages;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrganizationAcls(string $accessToken): array
    {
        $response = $this->apiGet('/organizationAcls', $accessToken, [
            'q' => 'roleAssignee',
            'state' => 'APPROVED',
            'count' => 100,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to list LinkedIn organizations: '.$response->body());
        }

        return $response->json('elements', []);
    }

    /**
     * @return array{name: string, category: ?string, picture_url: ?string}
     */
    public function getOrganization(string $organizationId, string $accessToken): array
    {
        $response = $this->apiGet('/organizations/'.$organizationId, $accessToken);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch LinkedIn organization: '.$response->body());
        }

        $json = $response->json() ?? [];
        $name = $json['localizedName']
            ?? data_get($json, 'name.localized.en_US')
            ?? data_get($json, 'vanityName')
            ?? ('LinkedIn Page '.$organizationId);

        $logoUrn = data_get($json, 'logoV2.original')
            ?? data_get($json, 'logoV2.cropped')
            ?? data_get($json, 'coverPhotoV2.original');

        return [
            'name' => (string) $name,
            'category' => data_get($json, 'primaryOrganizationType') ?: 'LinkedIn Page',
            'picture_url' => is_string($logoUrn) ? null : null,
        ];
    }

    public function resolveAccessToken(SocialPage $page): string
    {
        $account = $page->socialAccount;

        if (! $account instanceof SocialAccount) {
            return $page->access_token;
        }

        $expiresAt = $account->token_expires_at;
        $stillValid = $expiresAt instanceof Carbon && $expiresAt->isAfter(now()->addMinutes(5));

        if ($stillValid || ! $account->refresh_token) {
            return $account->access_token ?: $page->access_token;
        }

        $refreshed = $this->refreshAccessToken($account->refresh_token);
        $accessToken = $refreshed['access_token'];

        $account->update([
            'access_token' => $accessToken,
            'refresh_token' => $refreshed['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 5184000)),
        ]);

        $page->update(['access_token' => $accessToken]);

        SocialPage::query()
            ->where('social_account_id', $account->id)
            ->where('provider', 'linkedin')
            ->update(['access_token' => $accessToken]);

        return $accessToken;
    }

    /**
     * @return array{id: ?string}
     */
    public function publishTextPost(string $organizationId, string $accessToken, string $commentary): array
    {
        return $this->createPost($organizationId, $accessToken, $commentary);
    }

    /**
     * @return array{id: ?string}
     */
    public function publishImagePost(
        string $organizationId,
        string $accessToken,
        string $filePath,
        ?string $commentary = null,
        ?string $title = null
    ): array {
        $imageUrn = $this->uploadImage($organizationId, $accessToken, $filePath);

        return $this->createPost($organizationId, $accessToken, $commentary ?? '', [
            'media' => [
                'title' => $title ?: 'Image',
                'id' => $imageUrn,
            ],
        ]);
    }

    /**
     * @return array{id: ?string}
     */
    public function publishVideoPost(
        string $organizationId,
        string $accessToken,
        string $filePath,
        ?string $commentary = null,
        ?string $title = null
    ): array {
        $videoUrn = $this->uploadVideo($organizationId, $accessToken, $filePath);

        return $this->createPost($organizationId, $accessToken, $commentary ?? '', [
            'media' => [
                'title' => $title ?: 'Video',
                'id' => $videoUrn,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $content
     * @return array{id: ?string}
     */
    private function createPost(
        string $organizationId,
        string $accessToken,
        string $commentary,
        ?array $content = null
    ): array {
        $payload = [
            'author' => 'urn:li:organization:'.$organizationId,
            'commentary' => $commentary,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        if ($content !== null) {
            $payload['content'] = $content;
        }

        $response = $this->apiPost('/posts', $accessToken, $payload);

        if ($response->status() !== 201 && ! $response->successful()) {
            throw new RuntimeException('Failed to publish LinkedIn post: '.$response->body());
        }

        $postId = $response->header('x-restli-id')
            ?: $response->header('X-RestLi-Id')
            ?: $response->json('id');

        return ['id' => $postId ? (string) $postId : null];
    }

    private function uploadImage(string $organizationId, string $accessToken, string $filePath): string
    {
        if (! is_readable($filePath)) {
            throw new RuntimeException('LinkedIn image file is not readable.');
        }

        $init = $this->apiPost('/images?action=initializeUpload', $accessToken, [
            'initializeUploadRequest' => [
                'owner' => 'urn:li:organization:'.$organizationId,
            ],
        ]);

        if (! $init->successful() || ! $init->json('value.uploadUrl') || ! $init->json('value.image')) {
            throw new RuntimeException('Failed to initialize LinkedIn image upload: '.$init->body());
        }

        $uploadUrl = $init->json('value.uploadUrl');
        $imageUrn = $init->json('value.image');
        $binary = file_get_contents($filePath);

        if ($binary === false) {
            throw new RuntimeException('Unable to read LinkedIn image file.');
        }

        $upload = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
            'Content-Type' => 'application/octet-stream',
        ])->withBody($binary, 'application/octet-stream')
            ->timeout(120)
            ->put($uploadUrl);

        if (! $upload->successful()) {
            throw new RuntimeException('Failed to upload LinkedIn image: '.$upload->body());
        }

        return (string) $imageUrn;
    }

    private function uploadVideo(string $organizationId, string $accessToken, string $filePath): string
    {
        if (! is_readable($filePath)) {
            throw new RuntimeException('LinkedIn video file is not readable.');
        }

        $fileSize = filesize($filePath);

        if ($fileSize === false || $fileSize < 1) {
            throw new RuntimeException('Unable to determine LinkedIn video size.');
        }

        $init = $this->apiPost('/videos?action=initializeUpload', $accessToken, [
            'initializeUploadRequest' => [
                'owner' => 'urn:li:organization:'.$organizationId,
                'fileSizeBytes' => $fileSize,
                'uploadCaptions' => false,
                'uploadThumbnail' => false,
            ],
        ]);

        if (! $init->successful() || ! $init->json('value.video')) {
            throw new RuntimeException('Failed to initialize LinkedIn video upload: '.$init->body());
        }

        $videoUrn = $init->json('value.video');
        $uploadToken = $init->json('value.uploadToken') ?? '';
        $instructions = $init->json('value.uploadInstructions', []);
        $uploadedPartIds = [];

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open LinkedIn video file.');
        }

        try {
            foreach ($instructions as $part) {
                $first = (int) ($part['firstByte'] ?? 0);
                $last = (int) ($part['lastByte'] ?? 0);
                $uploadUrl = $part['uploadUrl'] ?? null;
                $length = $last - $first + 1;

                if (! $uploadUrl || $length < 1) {
                    throw new RuntimeException('Invalid LinkedIn video upload instruction.');
                }

                fseek($handle, $first);
                $chunk = fread($handle, $length);

                if ($chunk === false || strlen($chunk) === 0) {
                    throw new RuntimeException('Failed reading LinkedIn video chunk.');
                }

                $upload = Http::withHeaders([
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/octet-stream',
                ])->withBody($chunk, 'application/octet-stream')
                    ->timeout(300)
                    ->put($uploadUrl);

                if (! $upload->successful()) {
                    throw new RuntimeException('LinkedIn video chunk upload failed: '.$upload->body());
                }

                $etag = $upload->header('ETag') ?: $upload->header('etag');
                $uploadedPartIds[] = $etag ? trim($etag, '"') : '';
            }
        } finally {
            fclose($handle);
        }

        $finalize = $this->apiPost('/videos?action=finalizeUpload', $accessToken, [
            'finalizeUploadRequest' => [
                'video' => $videoUrn,
                'uploadToken' => $uploadToken,
                'uploadedPartIds' => $uploadedPartIds,
            ],
        ]);

        if (! $finalize->successful() && $finalize->status() !== 200) {
            throw new RuntimeException('Failed to finalize LinkedIn video upload: '.$finalize->body());
        }

        $this->waitForVideoAvailable((string) $videoUrn, $accessToken);

        return (string) $videoUrn;
    }

    private function waitForVideoAvailable(string $videoUrn, string $accessToken, int $attempts = 40): void
    {
        $encoded = rawurlencode($videoUrn);

        for ($i = 0; $i < $attempts; $i++) {
            $status = $this->apiGet('/videos/'.$encoded, $accessToken);

            if ($status->successful()) {
                $state = $status->json('status');

                if ($state === 'AVAILABLE') {
                    return;
                }

                if ($state === 'PROCESSING_FAILED') {
                    throw new RuntimeException(
                        'LinkedIn video processing failed: '.($status->json('processingFailureReason') ?: $status->body())
                    );
                }
            }

            sleep(2);
        }

        throw new RuntimeException('Timed out waiting for LinkedIn video processing.');
    }

    public function generateState(): string
    {
        return Str::random(40);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function apiGet(string $path, string $accessToken, array $query = [], string $base = self::API_BASE): Response
    {
        return Http::withHeaders($this->apiHeaders($accessToken))
            ->get(rtrim($base, '/').$path, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apiPost(string $path, string $accessToken, array $payload): Response
    {
        return Http::withHeaders(array_merge($this->apiHeaders($accessToken), [
            'Content-Type' => 'application/json',
        ]))->timeout(120)->post(self::API_BASE.$path, $payload);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(string $accessToken): array
    {
        return [
            'Authorization' => 'Bearer '.$accessToken,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Linkedin-Version' => $this->apiVersion(),
        ];
    }

    private function clientId(): string
    {
        $id = config('services.linkedin.client_id');

        if (! $id) {
            throw new RuntimeException('LINKEDIN_CLIENT_ID is not configured.');
        }

        return $id;
    }

    private function clientSecret(): string
    {
        $secret = config('services.linkedin.client_secret');

        if (! $secret) {
            throw new RuntimeException('LINKEDIN_CLIENT_SECRET is not configured.');
        }

        return $secret;
    }

    private function redirectUri(): string
    {
        return (string) config('services.linkedin.redirect');
    }

    private function apiVersion(): string
    {
        return (string) config('services.linkedin.api_version', '202608');
    }
}
