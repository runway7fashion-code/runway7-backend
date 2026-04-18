<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

// Register /broadcasting/auth with Sanctum-aware middleware so BOTH the admin
// panel (session cookie on stateful domain) and the mobile app (Bearer token)
// can authorize private channels.
Broadcast::routes(['middleware' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'auth:sanctum',
]]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Per-user inbox channel: receives events (NewMessage, UserTyping, MessagesRead,
// MessagesDelivered) for every conversation the user participates in. Used by the
// chat list screen so we don't have to subscribe to each conversation individually.
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
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
