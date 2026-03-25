<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAssistant extends Model
{
    protected $fillable = [
        'media_id', 'event_id', 'full_name', 'document_id', 'phone', 'email',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(User::class, 'media_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
