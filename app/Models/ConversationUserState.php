<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationUserState extends Model
{
    protected $table = 'conversation_user_state';

    protected $fillable = [
        'conversation_id', 'user_id',
        'archived_at', 'favorited_at', 'pinned_at',
    ];

    protected $casts = [
        'archived_at'  => 'datetime',
        'favorited_at' => 'datetime',
        'pinned_at'    => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
