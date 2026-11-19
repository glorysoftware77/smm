<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class YouTubeService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://www.googleapis.com/youtube/v3';

    private const ANALYTICS_BASE = 'https://youtubeanalytics.googleapis.com/v2';

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
                'https://www.googleapis.com/auth/yt-analytics.readonly',
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
            'uploads_playlist' => data_get($channel, 'contentDetails.relatedPlaylists.uploads'),
            'subscriber_count' => data_get($channel, 'statistics.subscriberCount'),
        ];
    }

    /**
     * Fresh access token for a connected YouTube page, refreshing when needed.
     */
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
            'token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600)),
        ]);

        $page->update(['access_token' => $accessToken]);

        return $accessToken;
    }

    /**
     * Videos published in the window, with lifetime stats + range analytics when available.
     *
     * @return array{content: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function getAccountInsights(string $accessToken, string $channelId, int $since, int $until, int $maxItems = 50): array
    {
        $videos = $this->getUploadsInRange($accessToken, $channelId, $since, $until, $maxItems);
        $analyticsByVideo = $this->getVideoAnalytics($accessToken, $since, $until);
        $channelSummary = $this->getChannelAnalyticsSummary($accessToken, $since, $until);

        $channel = $this->getMyChannelWithStats($accessToken);
        $channelSummary['subscribers'] = $channel['subscriber_count'] ?? null;
        $channelSummary['channel_title'] = $channel['title'] ?? null;

        $content = [];

        foreach ($videos as $video) {
            $id = $video['id'];
            $range = $analyticsByVideo[$id] ?? [];

            $content[] = [
                'id' => $id,
                'title' => $video['title'],
                'description' => $video['description'],
                'thumbnail_url' => $video['thumbnail_url'],
                'permalink' => 'https://www.youtube.com/watch?v='.$id,
                'published_at' => $video['published_at'],
                'type' => $video['is_short'] ? 'SHORT' : 'VIDEO',
                'views' => $range['views'] ?? $video['view_count'],
                'likes' => $range['likes'] ?? $video['like_count'],
                'comments' => $range['comments'] ?? $video['comment_count'],
                'shares' => $range['shares'] ?? null,
                'watch_minutes' => $range['estimatedMinutesWatched'] ?? null,
                'avg_view_duration' => $range['averageViewDuration'] ?? null,
                'from_range_analytics' => $range !== [],
            ];
        }

        return [
            'content' => $content,
            'summary' => $channelSummary,
        ];
    }

    /**
     * @return array{channel_id: string, title: string, thumbnail: ?string, uploads_playlist: ?string, subscriber_count: ?int}
     */
    private function getMyChannelWithStats(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::API_BASE.'/channels', [
            'part' => 'snippet,contentDetails,statistics',
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
            'uploads_playlist' => data_get($channel, 'contentDetails.relatedPlaylists.uploads'),
            'subscriber_count' => isset($channel['statistics']['subscriberCount'])
                ? (int) $channel['statistics']['subscriberCount']
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getUploadsInRange(
        string $accessToken,
        string $channelId,
        int $since,
        int $until,
        int $maxItems
    ): array {
        $channel = $this->getMyChannelWithStats($accessToken);
        $playlistId = $channel['uploads_playlist'];

        if (! $playlistId) {
            return [];
        }

        $videoIds = [];
        $pageToken = null;

        do {
            $response = Http::withToken($accessToken)->get(self::API_BASE.'/playlistItems', [
                'part' => 'contentDetails,snippet',
                'playlistId' => $playlistId,
                'maxResults' => 50,
                'pageToken' => $pageToken,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Failed to list YouTube uploads: '.$response->body());
            }

            foreach ($response->json('items', []) as $item) {
                $published = strtotime((string) data_get($item, 'contentDetails.videoPublishedAt', data_get($item, 'snippet.publishedAt')));

                if ($published === false) {
                    continue;
                }

                if ($published > $until) {
                    continue;
                }

                if ($published < $since) {
                    // Uploads playlist is newest-first.
                    break 2;
                }

                $videoIds[] = data_get($item, 'contentDetails.videoId');

                if (count($videoIds) >= $maxItems) {
                    break 2;
                }
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        $videoIds = array_values(array_filter($videoIds));

        if ($videoIds === []) {
            return [];
        }

        $videos = [];

        foreach (array_chunk($videoIds, 50) as $chunk) {
            $details = Http::withToken($accessToken)->get(self::API_BASE.'/videos', [
                'part' => 'snippet,statistics,contentDetails',
                'id' => implode(',', $chunk),
            ]);

            if (! $details->successful()) {
                throw new RuntimeException('Failed to fetch YouTube video details: '.$details->body());
            }

            foreach ($details->json('items', []) as $video) {
                $title = (string) data_get($video, 'snippet.title', 'Untitled');
                $duration = (string) data_get($video, 'contentDetails.duration', '');

                $videos[] = [
                    'id' => $video['id'],
                    'title' => $title,
                    'description' => data_get($video, 'snippet.description'),
                    'thumbnail_url' => data_get($video, 'snippet.thumbnails.medium.url')
                        ?? data_get($video, 'snippet.thumbnails.default.url'),
                    'published_at' => data_get($video, 'snippet.publishedAt'),
                    'view_count' => isset($video['statistics']['viewCount']) ? (int) $video['statistics']['viewCount'] : null,
                    'like_count' => isset($video['statistics']['likeCount']) ? (int) $video['statistics']['likeCount'] : null,
                    'comment_count' => isset($video['statistics']['commentCount']) ? (int) $video['statistics']['commentCount'] : null,
                    'is_short' => $this->looksLikeShort($title, $duration),
                ];
            }
        }

        return $videos;
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function getVideoAnalytics(string $accessToken, int $since, int $until): array
    {
        $response = Http::withToken($accessToken)->get(self::ANALYTICS_BASE.'/reports', [
            'ids' => 'channel==MINE',
            'startDate' => gmdate('Y-m-d', $since),
            'endDate' => gmdate('Y-m-d', $until),
            'metrics' => 'views,likes,comments,shares,estimatedMinutesWatched,averageViewDuration',
            'dimensions' => 'video',
            'sort' => '-views',
            'maxResults' => 50,
        ]);

        if (! $response->successful()) {
            Log::warning('YouTube video analytics failed', ['body' => $response->body()]);

            return [];
        }

        $headers = $response->json('columnHeaders', []);
        $names = array_map(fn ($h) => $h['name'] ?? null, $headers);
        $byVideo = [];

        foreach ($response->json('rows', []) as $row) {
            $mapped = [];

            foreach ($names as $index => $name) {
                if ($name) {
                    $mapped[$name] = $row[$index] ?? null;
                }
            }

            $videoId = $mapped['video'] ?? null;

            if (! $videoId) {
                continue;
            }

            $byVideo[$videoId] = $mapped;
        }

        return $byVideo;
    }

    /**
     * @return array<string, mixed>
     */
    private function getChannelAnalyticsSummary(string $accessToken, int $since, int $until): array
    {
        $response = Http::withToken($accessToken)->get(self::ANALYTICS_BASE.'/reports', [
            'ids' => 'channel==MINE',
            'startDate' => gmdate('Y-m-d', $since),
            'endDate' => gmdate('Y-m-d', $until),
            'metrics' => 'views,likes,comments,shares,subscribersGained,subscribersLost,estimatedMinutesWatched',
        ]);

        if (! $response->successful()) {
            Log::warning('YouTube channel analytics failed', ['body' => $response->body()]);

            return [];
        }

        $headers = $response->json('columnHeaders', []);
        $row = $response->json('rows.0', []);
        $summary = [];

        foreach ($headers as $index => $header) {
            $name = $header['name'] ?? null;

            if ($name && array_key_exists($index, $row)) {
                $summary[$name] = $row[$index];
            }
        }

        return $summary;
    }

    private function looksLikeShort(string $title, string $isoDuration): bool
    {
        if (str_contains(strtolower($title), '#shorts')) {
            return true;
        }

        if (preg_match('/PT(?:(\d+)M)?(?:(\d+)S)?/', $isoDuration, $matches)) {
            $seconds = ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0);

            return $seconds > 0 && $seconds <= 60;
        }

        return false;
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
