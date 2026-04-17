<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_a_id', 'user_b_id', 'show_id',
        'context_type', 'context_id',
        'status', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    // Participants
    public function userA()    { return $this->belongsTo(User::class, 'user_a_id'); }
    public function userB()    { return $this->belongsTo(User::class, 'user_b_id'); }
    public function show()     { return $this->belongsTo(Show::class); }

    // Backward compat aliases (casting conversations still reference model/designer)
    public function model()    { return $this->belongsTo(User::class, 'user_a_id'); }
    public function designer() { return $this->belongsTo(User::class, 'user_b_id'); }

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
        return $this->user_a_id === $userId ? $this->userB : $this->userA;
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->user_a_id === $userId || $this->user_b_id === $userId;
    }

    /**
     * Find or create a general conversation between two users.
     * For material observations, pass context_type='material'.
     */
    public static function findOrCreateBetween(int $userAId, int $userBId): self
    {
        $conversation = self::where(function ($q) use ($userAId, $userBId) {
                $q->where('user_a_id', $userAId)->where('user_b_id', $userBId);
            })
            ->orWhere(function ($q) use ($userAId, $userBId) {
                $q->where('user_a_id', $userBId)->where('user_b_id', $userAId);
            })
            ->where('status', 'active')
            ->first();

        if ($conversation) return $conversation;

        return self::create([
            'user_a_id' => $userAId,
            'user_b_id' => $userBId,
            'status'    => 'active',
        ]);
    }

    /**
     * Scope: conversations for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
