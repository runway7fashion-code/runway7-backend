<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_id', 'designer_id', 'show_id', 'status', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function model()    { return $this->belongsTo(User::class, 'model_id'); }
    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function show()     { return $this->belongsTo(Show::class); }

    public function messages()     { return $this->hasMany(Message::class)->orderBy('created_at'); }
    public function lastMessage()  { return $this->hasOne(Message::class)->latestOfMany(); }

    public function unreadCountFor(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function getOtherParticipant(int $userId): User
    {
        return $this->model_id === $userId ? $this->designer : $this->model;
    }
}
