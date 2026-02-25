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
     * Crear conversación cuando modelo acepta solicitud de diseñador.
     */
    public function createConversationFromShowAcceptance(Show $show, User $model, User $designer): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'model_id'    => $model->id,
                'designer_id' => $designer->id,
                'show_id'     => $show->id,
            ],
            [
                'status' => 'active',
            ]
        );
    }

    /**
     * Enviar mensaje en una conversación.
     */
    public function sendMessage(Conversation $conversation, User $sender, string $body, string $type = 'text', ?string $imageUrl = null): Message
    {
        if ($sender->id !== $conversation->model_id && $sender->id !== $conversation->designer_id) {
            throw new \Exception('No tienes permiso para enviar mensajes en esta conversación.');
        }

        if ($conversation->status !== 'active') {
            throw new \Exception('Esta conversación no está activa.');
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
     * Marcar mensajes como leídos.
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
     * Obtener conversaciones de un usuario.
     */
    public function getConversationsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $query = Conversation::with(['model', 'designer', 'show.eventDay', 'lastMessage'])
            ->where('status', 'active');

        if ($user->role === 'model') {
            $query->where('model_id', $user->id);
        } elseif ($user->role === 'designer') {
            $query->where('designer_id', $user->id);
        }

        return $query->orderByDesc('last_message_at')->get();
    }
}
