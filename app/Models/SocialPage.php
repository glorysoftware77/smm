<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPage extends Model
{
    protected $fillable = [
        'user_id',
        'social_account_id',
        'linked_social_page_id',
        'provider',
        'page_id',
        'name',
        'category',
        'picture_url',
        'access_token',
        'is_connected',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'is_connected' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function linkedSocialPage(): BelongsTo
    {
        return $this->belongsTo(SocialPage::class, 'linked_social_page_id');
    }
}
