<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (!$conversation) return false;

    // Both chat participants can subscribe. Admins get a moderation view.
    if ((int) $user->id === (int) $conversation->user_a_id) return true;
    if ((int) $user->id === (int) $conversation->user_b_id) return true;
    if ($user->role === 'admin' || $user->role === 'operation') return true;

    return false;
});
