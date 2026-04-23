<?php

namespace App\Services;

use App\Events\MessagesDelivered;
use App\Events\MessagesRead;
use App\Events\NewMessage;
use App\Jobs\SendChatMessageNotificationJob;
use App\Models\ChatSupportAssignment;
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

        // Lazy re-route: if this is a support chat and the counterpart agent is
        // now inactive, transparently reassign to an active agent before delivering.
        // Skip for system messages (reassignment itself emits one, avoid recursion).
        if ($type !== 'system' && !$sender->isInternalTeam()) {
            $conversation = $this->maybeLazyReassign($conversation, $sender);
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

        // Also collapse any unread in-app chat notifications for this conversation —
        // once the user has opened the chat, the aggregated notification is stale.
        \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $reader->id)
            ->whereNull('read_at')
            ->whereRaw("data::jsonb ->> 'conversation_id' = ?", [(string) $conversation->id])
            ->update(['read_at' => $now, 'updated_at' => $now]);

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

    /**
     * Resolve which operation user should handle a support chat from $requester.
     *
     *   1. Look up users assigned to $requester->role in chat_support_assignments.
     *   2. Filter by active status and not soft-deleted.
     *   3. Pick the one with the fewest active support conversations (load balance).
     *   4. Fallback: any active operation user, then any admin.
     *   5. Returns null if none available.
     */
    public function resolveSupportAgent(User $requester): ?User
    {
        $assignedIds = ChatSupportAssignment::where('role', $requester->role)->pluck('user_id');

        if ($assignedIds->isNotEmpty()) {
            $candidates = User::whereIn('id', $assignedIds)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->get();

            if ($candidates->isNotEmpty()) {
                return $this->pickLeastLoaded($candidates);
            }
        }

        return User::where('role', 'operation')->where('status', 'active')->first()
            ?? User::where('role', 'admin')->where('status', 'active')->first();
    }

    /**
     * Given a collection of candidate support agents, return the one that has
     * the fewest unread conversations assigned to them right now.
     */
    private function pickLeastLoaded(\Illuminate\Support\Collection $candidates): User
    {
        $counts = $candidates->mapWithKeys(function (User $user) {
            $convIds = Conversation::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
                ->active()
                ->pluck('id');

            $unread = Message::whereIn('conversation_id', $convIds)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->count();

            return [$user->id => $unread];
        });

        $minId = $counts->sort()->keys()->first();

        return $candidates->firstWhere('id', $minId);
    }

    /**
     * If the conversation's operation-side participant is inactive, reassign to
     * an active agent for the sender's role. No-op if the counterpart is still
     * active, or if this is not a support conversation.
     */
    private function maybeLazyReassign(Conversation $conversation, User $sender): Conversation
    {
        $counterpartId = $conversation->user_a_id === $sender->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        $counterpart = User::find($counterpartId);
        if (!$counterpart || !$counterpart->isInternalTeam()) {
            return $conversation;
        }

        if ($counterpart->status === 'active' && !$counterpart->trashed()) {
            return $conversation;
        }

        $agent = $this->resolveSupportAgent($sender);
        if (!$agent || $agent->id === $counterpart->id) {
            return $conversation;
        }

        return $this->reassignSupportConversation($conversation, $agent, $sender);
    }

    /**
     * Reassign a support conversation to a new operation user. Replaces the
     * previous operation participant (whichever side they were on) and inserts
     * a system message to record the change in the chat history.
     */
    public function reassignSupportConversation(Conversation $conversation, User $newAgent, ?User $actor = null): Conversation
    {
        $userA = User::find($conversation->user_a_id);
        $userB = User::find($conversation->user_b_id);

        // The operation-side participant is whichever one has an internal role.
        $operationSide = $userA && $userA->isInternalTeam() ? 'user_a_id'
            : ($userB && $userB->isInternalTeam() ? 'user_b_id' : null);

        if (!$operationSide) {
            throw new \Exception('This conversation has no operation participant to reassign.');
        }

        $previous = $conversation->{$operationSide} === $userA?->id ? $userA : $userB;

        if ($previous->id === $newAgent->id) {
            return $conversation;
        }

        // Keep pair normalized (min = user_a, max = user_b) so findOrCreateBetween
        // stays consistent for future lookups.
        $counterpartId = $conversation->user_a_id === $previous->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        $conversation->update([
            'user_a_id' => min($newAgent->id, $counterpartId),
            'user_b_id' => max($newAgent->id, $counterpartId),
        ]);

        $actorLabel = $actor ? trim($actor->first_name . ' ' . $actor->last_name) : 'operation';
        $newLabel = trim($newAgent->first_name . ' ' . $newAgent->last_name);
        $this->sendMessage(
            $conversation,
            $actor ?? $newAgent,
            "Conversation reassigned to {$newLabel} by {$actorLabel}.",
            'system'
        );

        return $conversation->fresh();
    }
}
