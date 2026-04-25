<?php

namespace App\Services;

use App\Events\MessagesDelivered;
use App\Events\MessagesRead;
use App\Events\NewMessage;
use App\Jobs\SendChatMessageNotificationJob;
use App\Models\ChatSupportAssignment;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        if (!$sender->isInternalTeam()) {
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
     * Get conversations for a user, with optional search + filter.
     *
     *   filter:
     *     all       — everything except archived (default)
     *     unread    — only conversations with unread messages from the other side
     *     favorites — favorited
     *     archived  — archived
     *     groups    — group conversations (Phase B; placeholder for now)
     *   search: matches against the other participant's first/last name
     *           or last message body (case-insensitive).
     *
     * Sort order: pinned conversations first (by pinned_at desc), then everything
     * else by last_message_at desc.
     */
    public function getConversationsForUser(User $user, ?string $filter = 'all', ?string $search = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Conversation::with(['userA', 'userB', 'show.eventDay', 'lastMessage'])
            ->forUser($user->id)
            ->active()
            ->leftJoin('conversation_user_state as cus', function ($join) use ($user) {
                $join->on('cus.conversation_id', '=', 'conversations.id')
                    ->where('cus.user_id', '=', $user->id);
            })
            ->select('conversations.*', 'cus.archived_at as my_archived_at', 'cus.favorited_at as my_favorited_at', 'cus.pinned_at as my_pinned_at');

        match ($filter) {
            'archived'  => $query->whereNotNull('cus.archived_at'),
            'favorites' => $query->whereNotNull('cus.favorited_at')->whereNull('cus.archived_at'),
            'unread'    => $query->whereExists(function ($q) use ($user) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('messages')
                  ->whereColumn('messages.conversation_id', 'conversations.id')
                  ->where('messages.sender_id', '!=', $user->id)
                  ->where('messages.is_read', false);
            })->whereNull('cus.archived_at'),
            'groups'    => $query->where('conversations.is_group', true)->whereNull('cus.archived_at'),
            default     => $query->whereNull('cus.archived_at'),
        };

        if ($search) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $otherIdExpr = \Illuminate\Support\Facades\DB::raw('CASE WHEN user_a_id = ' . (int) $user->id . ' THEN user_b_id ELSE user_a_id END');
            $query->where(function ($q) use ($like, $otherIdExpr) {
                $q->whereExists(function ($sub) use ($like, $otherIdExpr) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('users as u')
                        ->whereColumn('u.id', $otherIdExpr)
                        ->where(function ($n) use ($like) {
                            $n->where('u.first_name', 'ilike', $like)
                              ->orWhere('u.last_name', 'ilike', $like)
                              ->orWhereRaw("(u.first_name || ' ' || u.last_name) ilike ?", [$like]);
                        });
                })->orWhereExists(function ($sub) use ($like) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('messages')
                        ->whereColumn('messages.conversation_id', 'conversations.id')
                        ->where('messages.body', 'ilike', $like);
                });
            });
        }

        return $query
            ->orderByRaw('cus.pinned_at IS NULL, cus.pinned_at DESC NULLS LAST')
            ->orderByDesc('conversations.last_message_at')
            ->get();
    }

    // ───────────────────────────────────────────── GROUPS ─────────────────────────────────────────────

    /**
     * Create a group conversation. Validation is the caller's responsibility
     * (the controller knows whether the creator is an internal user or a designer
     * and applies the appropriate restrictions). The creator is added as 'admin';
     * everyone else as 'member'.
     */
    public function createGroup(User $creator, string $name, array $memberIds, ?int $showId = null): Conversation
    {
        return DB::transaction(function () use ($creator, $name, $memberIds, $showId) {
            $conversation = Conversation::create([
                'is_group'      => true,
                'name'          => $name,
                'created_by_id' => $creator->id,
                'show_id'       => $showId,
                'status'        => 'active',
                'last_message_at' => now(),
            ]);

            // Creator first as admin, then unique members as members.
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $creator->id,
                'role'            => 'admin',
                'joined_at'       => now(),
            ]);
            foreach (array_unique(array_filter($memberIds)) as $uid) {
                if ($uid === $creator->id) continue;
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $uid,
                    'role'            => 'member',
                    'joined_at'       => now(),
                ]);
            }

            return $conversation->fresh();
        });
    }

    /**
     * Add a member to a group. Only the creator can call this from the API.
     */
    public function addMember(Conversation $conversation, User $member): ConversationParticipant
    {
        if (!$conversation->is_group) {
            throw new \Exception('Only group conversations support members.');
        }

        return ConversationParticipant::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $member->id],
            ['role' => 'member', 'joined_at' => now(), 'left_at' => null]
        );
    }

    /**
     * Remove a member from a group (sets left_at).
     */
    public function removeMember(Conversation $conversation, int $userId): void
    {
        if (!$conversation->is_group) return;

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->update(['left_at' => now()]);
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

        if ($counterpart->status === 'active') {
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

        // Friendly handoff message from the new agent — rendered as a normal
        // chat bubble so mobile clients show it without needing to implement
        // a separate system-message renderer.
        $greeting = "Hi! I'm {$newAgent->first_name}, I'll be taking over this conversation from now on.";
        $this->sendMessage($conversation, $newAgent, $greeting);

        return $conversation->fresh();
    }
}
