<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
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
        $conversations = $this->chatService->getConversationsForUser($user);

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
                ],
                'last_message' => $c->lastMessage ? [
                    'body'       => $c->lastMessage->body,
                    'type'       => $c->lastMessage->type,
                    'sender_id'  => $c->lastMessage->sender_id,
                    'created_at' => $c->lastMessage->created_at->toISOString(),
                ] : null,
                'unread_count'    => $c->unreadCountFor($user->id),
                'last_message_at' => $c->last_message_at?->toISOString(),
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

        // Find an Operation user (or admin as fallback)
        $operationUser = User::where('role', 'operation')->where('status', 'active')->first()
            ?? User::where('role', 'admin')->first();

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
                ],
                'last_message_at' => $conversation->last_message_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * Get messages for a conversation (paginated).
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $messages = $conversation->messages()
            ->with('sender:id,first_name,last_name,profile_picture')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($messages);
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
