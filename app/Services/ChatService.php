<?php

namespace App\Services;

use App\Events\MessagesRead;
use App\Events\NewMessage;
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
    public function createConversationFromShowAcceptance(Show $show, User $model, User $designer): Conversation
    {
        return Conversation::firstOrCreate(
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

        broadcast(new NewMessage($message->load('sender')))->toOthers();

        return $message;
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(Conversation $conversation, User $reader): int
    {
        $count = $conversation->messages()
            ->where('sender_id', '!=', $reader->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        if ($count > 0) {
            broadcast(new MessagesRead($conversation, $reader))->toOthers();
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
