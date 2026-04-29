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

// Sponsorship lead detail page — listens to delivery-status updates from Mailgun webhook.
// Cualquier usuario interno del área sponsorship o admin puede suscribirse al lead que esté viendo.
Broadcast::channel('sponsorship-lead.{leadId}', function ($user, int $leadId) {
    if (!$user) return false;
    if ($user->role === 'admin') return true;
    // Cross-area: usuarios con extra_areas que incluyan sponsorship son tratados como líder.
    if ($user->isLeaderOf('sponsorship')) return true;
    if ($user->role === 'sponsorship') {
        $lead = \App\Models\Sponsorship\Lead::find($leadId);
        if (!$lead) return false;
        // Asesor: solo si el lead está asignado a él (líder ya cubierto arriba).
        return (int) $lead->assigned_to_user_id === (int) $user->id;
    }
    return false;
});

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (!$conversation) return false;

    // 1:1 or group: any active participant can subscribe.
    if ($conversation->hasParticipant((int) $user->id)) return true;

    // Internal moderators get a read-only view.
    if ($user->role === 'admin' || $user->role === 'operation') return true;

    return false;
});
