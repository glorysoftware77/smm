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
}
