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
        'is_group', 'name', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'is_group'        => 'boolean',
        ];
    }

    // Participants
    public function userA()    { return $this->belongsTo(User::class, 'user_a_id'); }
    public function userB()    { return $this->belongsTo(User::class, 'user_b_id'); }
    public function show()     { return $this->belongsTo(Show::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by_id'); }

    // Group participants (only meaningful when is_group=true)
    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class)->whereNull('left_at');
    }

    public function allParticipants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Returns user IDs of every active participant of this conversation,
     * regardless of whether it is a 1:1 or a group. Used for fan-out of
     * broadcasts and notifications.
     */
    public function participantIds(): array
    {
        if ($this->is_group) {
            return $this->participants()->pluck('user_id')->all();
        }
        return array_filter([$this->user_a_id, $this->user_b_id]);
    }

    // Backward compat aliases (casting conversations still reference model/designer)
    public function model()    { return $this->belongsTo(User::class, 'user_a_id'); }
    public function designer() { return $this->belongsTo(User::class, 'user_b_id'); }

    public function messages()     { return $this->hasMany(Message::class)->orderBy('created_at'); }
    public function lastMessage()  { return $this->hasOne(Message::class)->latestOfMany(); }

    public function userStates() { return $this->hasMany(ConversationUserState::class); }

    public function stateFor(int $userId): ?ConversationUserState
    {
        return $this->userStates()->where('user_id', $userId)->first();
    }

    public function unreadCountFor(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function getOtherParticipant(int $userId): ?User
    {
        if ($this->is_group) return null; // groups have many participants, no "other"
        return $this->user_a_id === $userId ? $this->userB : $this->userA;
    }

    public function hasParticipant(int $userId): bool
    {
        if ($this->is_group) {
            return $this->participants()->where('user_id', $userId)->exists();
        }
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
     * Scope: conversations the user belongs to (1:1 via user_a/user_b OR group via pivot).
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('conversations.user_a_id', $userId)
              ->orWhere('conversations.user_b_id', $userId)
              ->orWhereExists(function ($sub) use ($userId) {
                  $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                      ->from('conversation_participants')
                      ->whereColumn('conversation_participants.conversation_id', 'conversations.id')
                      ->where('conversation_participants.user_id', $userId)
                      ->whereNull('conversation_participants.left_at');
              });
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
