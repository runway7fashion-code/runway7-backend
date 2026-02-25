<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'body', 'image_url', 'target_roles', 'target_event_id',
        'data', 'status', 'scheduled_at', 'sent_at', 'recipients_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'data' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function targetEvent() { return $this->belongsTo(Event::class, 'target_event_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
