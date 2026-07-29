<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'social_page_id',
        'message',
        'title',
        'media_type',
        'post_format',
        'media_path',
        'facebook_post_id',
        'facebook_video_id',
        'status',
        'error_message',
        'insights',
        'insights_fetched_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'insights_fetched_at' => 'datetime',
            'insights' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialPage(): BelongsTo
    {
        return $this->belongsTo(SocialPage::class);
    }

    public function insightValue(string $key): int|float|string|null
    {
        return $this->insights[$key] ?? null;
    }

    /**
     * Reads a metric by friendly name, falling back across the Graph API keys
     * that Meta uses for photos, videos and Reels.
     */
    public function metric(string $name): ?float
    {
        foreach (self::metricKeys($name) as $key) {
            $value = $this->insightValue($key);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function metricKeys(string $name): array
    {
        return match ($name) {
            'views' => ['post_media_view', 'post_video_views', 'video_views'],
            'reach' => ['post_total_media_view_unique', 'post_video_views_unique'],
            'from_followers' => ['views_from_followers'],
            'from_non_followers' => ['views_from_non_followers'],
            'reactions' => ['reactions'],
            'comments' => ['comments'],
            'shares' => ['shares'],
            'clicks' => ['post_clicks'],
            default => [$name],
        };
    }
}
