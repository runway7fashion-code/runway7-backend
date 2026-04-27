<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id', 'sender_id', 'body', 'type',
        'image_url',
        'attachment_url', 'attachment_mime', 'attachment_size', 'attachment_duration', 'attachment_name',
        'is_read', 'read_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read'             => 'boolean',
            'read_at'             => 'datetime',
            'delivered_at'        => 'datetime',
            'attachment_size'     => 'integer',
            'attachment_duration' => 'integer',
        ];
    }

    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function sender()       { return $this->belongsTo(User::class, 'sender_id'); }
}
