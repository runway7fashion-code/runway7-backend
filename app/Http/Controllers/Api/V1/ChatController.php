<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    /**
     * Listar conversaciones del usuario autenticado.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = $this->chatService->getConversationsForUser($user);

        $data = $conversations->map(fn(Conversation $c) => [
            'id'     => $c->id,
            'status' => $c->status,
            'other_participant' => [
                'id'              => $c->getOtherParticipant($user->id)->id,
                'name'            => $c->getOtherParticipant($user->id)->full_name,
                'profile_picture' => $c->getOtherParticipant($user->id)->profile_picture,
                'role'            => $c->getOtherParticipant($user->id)->role,
            ],
            'show' => [
                'id'   => $c->show->id,
                'name' => $c->show->name,
            ],
            'last_message' => $c->lastMessage ? [
                'body'       => $c->lastMessage->body,
                'type'       => $c->lastMessage->type,
                'sender_id'  => $c->lastMessage->sender_id,
                'created_at' => $c->lastMessage->created_at->toISOString(),
            ] : null,
            'unread_count'    => $c->unreadCountFor($user->id),
            'last_message_at' => $c->last_message_at?->toISOString(),
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Mensajes de una conversación (paginados).
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $conversation->model_id && $user->id !== $conversation->designer_id) {
            abort(403, 'No tienes acceso a esta conversación.');
        }

        $messages = $conversation->messages()
            ->with('sender:id,first_name,last_name,profile_picture')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($messages);
    }

    /**
     * Enviar mensaje.
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
                    'created_at'      => $message->created_at->toISOString(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Marcar mensajes como leídos.
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $conversation->model_id && $user->id !== $conversation->designer_id) {
            abort(403, 'No tienes acceso a esta conversación.');
        }

        $count = $this->chatService->markAsRead($conversation, $user);

        return response()->json(['read_count' => $count]);
    }
}
