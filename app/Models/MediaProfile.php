<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaProfile extends Model
{
    protected $fillable = [
        'user_id', 'category', 'portfolio_url', 'instagram', 'location',
        'will_travel', 'importance', 'max_assistants',
        'media_link_1', 'media_link_2', 'media_link_3', 'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
