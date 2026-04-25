<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationUserState;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    /**
     * List all conversations for the authenticated user.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $filter = $request->input('filter', 'all');
        $search = $request->input('search');

        $conversations = $this->chatService->getConversationsForUser($user, $filter, $search);

        $data = $conversations->map(function (Conversation $c) use ($user) {
            $other = $c->getOtherParticipant($user->id);

            $result = [
                'id'     => $c->id,
                'status' => $c->status,
                'context_type' => $c->context_type,
                'other_participant' => [
                    'id'              => $other->id,
                    'name'            => $other->full_name,
                    'profile_picture' => $other->profile_picture,
                    'role'            => $other->role,
                    'phone'           => in_array($other->role, User::ROLES_PARTICIPANT) ? $other->phone : null,
                    'is_online'       => $other->is_online,
                    'last_seen_at'    => $other->last_seen_at?->toISOString(),
                ],
                'last_message' => $c->lastMessage ? [
                    'body'       => $c->lastMessage->body,
                    'type'       => $c->lastMessage->type,
                    'sender_id'  => $c->lastMessage->sender_id,
                    'created_at' => $c->lastMessage->created_at->toISOString(),
                ] : null,
                'unread_count'    => $c->unreadCountFor($user->id),
                'last_message_at' => $c->last_message_at?->toISOString(),
                'is_archived'     => !is_null($c->my_archived_at ?? null),
                'is_favorited'    => !is_null($c->my_favorited_at ?? null),
                'is_pinned'       => !is_null($c->my_pinned_at ?? null),
            ];

            // Include show info for casting conversations
            if ($c->show) {
                $result['show'] = [
                    'id'   => $c->show->id,
                    'name' => $c->show->name,
                ];
            }

            return $result;
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Start (or resume) a general conversation with Operations support.
     * Only participants (model, designer, media, volunteer) can initiate.
     * Reuses existing conversation if one already exists.
     */
    public function startSupportChat(Request $request): JsonResponse
    {
        $user = $request->user();

        $allowedRoles = ['model', 'designer', 'media', 'volunteer'];
        if (!in_array($user->role, $allowedRoles)) {
            return response()->json(['message' => 'Only participants can start support chats.'], 403);
        }

        $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        // Route to the agent assigned to this requester's role (balanced),
        // falling back to any active operation user, then admin.
        $operationUser = $this->chatService->resolveSupportAgent($user);

        if (!$operationUser) {
            return response()->json(['message' => 'No support agents available.'], 503);
        }

        $conversation = Conversation::findOrCreateBetween($user->id, $operationUser->id);

        // Send initial message if provided
        if ($request->filled('message')) {
            $this->chatService->sendMessage($conversation, $user, $request->message);
            $conversation->refresh();
        }

        $other = $conversation->getOtherParticipant($user->id);

        return response()->json([
            'conversation' => [
                'id'     => $conversation->id,
                'status' => $conversation->status,
                'context_type' => $conversation->context_type,
                'other_participant' => [
                    'id'              => $other->id,
                    'name'            => $other->full_name,
                    'profile_picture' => $other->profile_picture,
                    'role'            => $other->role,
                    'phone'           => in_array($other->role, User::ROLES_PARTICIPANT) ? $other->phone : null,
                    'is_online'       => $other->is_online,
                    'last_seen_at'    => $other->last_seen_at?->toISOString(),
                ],
                'last_message_at' => $conversation->last_message_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * Get messages for a conversation in chronological order (oldest → newest).
     *
     * Optional query params:
     *   - limit=N (default: all messages)
     *   - before=<message_id> to page backward for scroll-up history
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $query = $conversation->messages()
            ->with('sender:id,first_name,last_name,profile_picture');

        if ($before = $request->input('before')) {
            $query->where('id', '<', $before);
        }

        $limit = (int) $request->input('limit', 0);

        // Take the most recent N (DESC) then reverse so the client gets oldest→newest
        if ($limit > 0) {
            $messages = $query->orderByDesc('created_at')->limit($limit)->get()->reverse()->values();
        } else {
            $messages = $query->orderBy('created_at')->get();
        }

        return response()->json(['data' => $messages]);
    }

    /**
     * Send a message.
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'body'      => 'required_without:image_url|string|max:2000',
            'type'      => 'in:text,image',
            'image_url' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        try {
            $message = $this->chatService->sendMessage(
                $conversation,
                $user,
                $request->input('body', ''),
                $request->input('type', 'text'),
                $request->input('image_url'),
            );

            return response()->json([
                'data' => [
                    'id'              => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id'       => $message->sender_id,
                    'body'            => $message->body,
                    'type'            => $message->type,
                    'image_url'       => $message->image_url,
                    'is_read'         => false,
                    'read_at'         => null,
                    'delivered_at'    => null,
                    'created_at'      => $message->created_at->toISOString(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Mark the authenticated user as actively viewing a conversation.
     * Used by SendChatMessageNotificationJob to suppress push/sms/in-app
     * notifications while the recipient already has the chat open.
     */
    public function focus(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $user->forceFill([
            'active_conversation_id' => $conversation->id,
            'active_conversation_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Clear the active conversation when the user leaves the chat screen.
     */
    public function blur(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'active_conversation_id' => null,
            'active_conversation_at' => null,
        ])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Emit a "typing" realtime event to the other participant. No DB write.
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $isTyping = (bool) $request->input('is_typing', true);

        try {
            broadcast(new UserTyping($conversation, $user, $isTyping))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('UserTyping broadcast failed: ' . $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse   { return $this->setState($request, $conversation, 'archived', true); }
    public function unarchive(Request $request, Conversation $conversation): JsonResponse { return $this->setState($request, $conversation, 'archived', false); }
    public function favorite(Request $request, Conversation $conversation): JsonResponse  { return $this->setState($request, $conversation, 'favorited', true); }
    public function unfavorite(Request $request, Conversation $conversation): JsonResponse{ return $this->setState($request, $conversation, 'favorited', false); }
    public function pin(Request $request, Conversation $conversation): JsonResponse       { return $this->setState($request, $conversation, 'pinned', true); }
    public function unpin(Request $request, Conversation $conversation): JsonResponse     { return $this->setState($request, $conversation, 'pinned', false); }

    /**
     * Toggle the per-user state of a conversation: archived/favorited/pinned.
     */
    private function setState(Request $request, Conversation $conversation, string $flag, bool $set): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $column = $flag . '_at';
        $state = ConversationUserState::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            []
        );
        $state->{$column} = $set ? now() : null;
        $state->save();

        return response()->json([
            'is_archived'  => !is_null($state->archived_at),
            'is_favorited' => !is_null($state->favorited_at),
            'is_pinned'    => !is_null($state->pinned_at),
        ]);
    }

    /**
     * Mark messages as delivered (called when device receives the push / WS event).
     */
    public function markAsDelivered(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $count = $this->chatService->markAsDelivered($conversation, $user);

        return response()->json(['delivered_count' => $count]);
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $count = $this->chatService->markAsRead($conversation, $user);

        return response()->json(['read_count' => $count]);
    }
}
