<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationUserState extends Model
{
    protected $table = 'conversation_user_state';

    protected $fillable = [
        'conversation_id', 'user_id',
        'archived_at', 'favorited_at', 'pinned_at', 'muted_until',
    ];

    protected $casts = [
        'archived_at'  => 'datetime',
        'favorited_at' => 'datetime',
        'pinned_at'    => 'datetime',
        'muted_until'  => 'datetime',
    ];

    public const MUTE_FOREVER = '2099-12-31 23:59:59';

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
