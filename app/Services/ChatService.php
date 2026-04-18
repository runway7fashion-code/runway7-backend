<?php

namespace App\Services;

use App\Events\MessagesDelivered;
use App\Events\MessagesRead;
use App\Events\NewMessage;
use App\Jobs\SendChatMessageNotificationJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Show;
use App\Models\User;

class ChatService
{
    /**
     * Create conversation when model accepts designer's show request.
     * Uses the generic table with context_type='casting'.
     */
    public function createConversationFromShowAcceptance(Show $show, User $model, User $designer, ?string $initialMessage = null): Conversation
    {
        $conversation = Conversation::firstOrCreate(
            [
                'user_a_id'    => $model->id,
                'user_b_id'    => $designer->id,
                'show_id'      => $show->id,
                'context_type' => 'casting',
            ],
            [
                'status' => 'active',
            ]
        );

        if ($conversation->wasRecentlyCreated && $initialMessage) {
            $this->sendMessage($conversation, $designer, $initialMessage);
        }

        return $conversation;
    }

    /**
     * Find or create a general conversation between two users.
     */
    public function findOrCreateConversation(int $userAId, int $userBId): Conversation
    {
        return Conversation::findOrCreateBetween($userAId, $userBId);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Conversation $conversation, User $sender, string $body, string $type = 'text', ?string $imageUrl = null): Message
    {
        if (!$conversation->hasParticipant($sender->id)) {
            throw new \Exception('You are not a participant of this conversation.');
        }

        if ($conversation->status !== 'active') {
            throw new \Exception('This conversation is not active.');
        }

        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body'      => $body,
            'type'      => $type,
            'image_url' => $imageUrl,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Broadcast realtime event — non-critical, don't fail the request if the WS server is unreachable
        try {
            broadcast(new NewMessage($message->load('sender')))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Chat broadcast failed: ' . $e->getMessage());
        }

        // Dispatch push + in-app notification to the recipient (system messages don't trigger notifications)
        if ($type !== 'system') {
            SendChatMessageNotificationJob::dispatch(messageId: $message->id);
        }

        return $message;
    }

    /**
     * Mark messages as delivered (recipient's device received them, e.g. on push receipt).
     */
    public function markAsDelivered(Conversation $conversation, User $recipient): int
    {
        $now = now();

        $count = $conversation->messages()
            ->where('sender_id', '!=', $recipient->id)
            ->whereNull('delivered_at')
            ->update([
                'delivered_at' => $now,
                'updated_at'   => $now,
            ]);

        if ($count > 0) {
            try {
                broadcast(new MessagesDelivered($conversation, $recipient))->toOthers();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('MessagesDelivered broadcast failed: ' . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Mark messages as read. Implies delivered.
     */
    public function markAsRead(Conversation $conversation, User $reader): int
    {
        $now = now();

        // Treat a "mark as read" as an implicit presence heartbeat — if the user
        // keeps opening/reading the chat, we know they are actively viewing it.
        $reader->forceFill([
            'active_conversation_id' => $conversation->id,
            'active_conversation_at' => $now,
        ])->save();

        // If a message is read, it must have been delivered — backfill delivered_at for consistency.
        $conversation->messages()
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => $now]);

        $count = $conversation->messages()
            ->where('sender_id', '!=', $reader->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => $now,
            ]);

        if ($count > 0) {
            try {
                broadcast(new MessagesRead($conversation, $reader))->toOthers();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('MessagesRead broadcast failed: ' . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Get conversations for a user (all types: casting, general, material).
     */
    public function getConversationsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Conversation::with(['userA', 'userB', 'show.eventDay', 'lastMessage'])
            ->forUser($user->id)
            ->active()
            ->orderByDesc('last_message_at')
            ->get();
    }
}
