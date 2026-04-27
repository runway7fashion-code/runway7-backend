<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendChatMessageNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $messageId,
    ) {}

    public function handle(FirebaseNotificationService $firebase): void
    {
        $message = Message::with(['conversation', 'sender'])->find($this->messageId);
        if (!$message) return;

        $conversation = $message->conversation;
        if (!$conversation) return;

        $sender = $message->sender;
        $senderName = $conversation->is_group && $conversation->name
            ? "{$conversation->name} · ".trim("{$sender->first_name} {$sender->last_name}")
            : trim("{$sender->first_name} {$sender->last_name}");

        // Build notification body (truncate long messages)
        $body = match ($message->type) {
            'image'    => '📷 Sent an image',
            'audio'    => '🎤 Voice message',
            'document' => '📎 ' . ($message->attachment_name ?: 'Sent a document'),
            'system'   => $message->body,
            default    => Str::limit($message->body, 100),
        };

        // Recipients: everyone in the conversation except the sender.
        $recipientIds = array_values(array_diff($conversation->participantIds(), [$message->sender_id]));

        foreach ($recipientIds as $recipientId) {
            $this->notifyRecipient($firebase, $conversation, $message, $sender, $senderName, $body, (int) $recipientId);
        }
    }

    private function notifyRecipient(
        FirebaseNotificationService $firebase,
        Conversation $conversation,
        Message $message,
        User $sender,
        string $senderName,
        string $body,
        int $recipientId,
    ): void {
        $recipient = User::find($recipientId);
        if (!$recipient) return;

        // Skip if the recipient is currently viewing this conversation.
        if (
            $recipient->active_conversation_id === $conversation->id
            && $recipient->active_conversation_at
            && $recipient->active_conversation_at->gt(now()->subSeconds(60))
        ) {
            return;
        }

        // Skip if the recipient muted this conversation.
        $mutedUntil = DB::table('conversation_user_state')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $recipientId)
            ->value('muted_until');
        if ($mutedUntil && \Carbon\Carbon::parse($mutedUntil)->isFuture()) {
            return;
        }

        // Aggregate in-app notification per (recipient, conversation) while unread.
        $existing = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $recipientId)
            ->whereNull('read_at')
            ->whereRaw("data::jsonb ->> 'conversation_id' = ?", [(string) $conversation->id])
            ->orderByDesc('created_at')
            ->first();

        $payload = [
            'title'           => $senderName,
            'body'            => $body,
            'screen'          => 'chat',
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
            'sender_id'       => $sender->id,
        ];

        if ($existing) {
            $existingData = json_decode($existing->data, true) ?: [];
            $payload['message_count'] = (int) ($existingData['message_count'] ?? 1) + 1;
            DB::table('notifications')->where('id', $existing->id)->update([
                'data'       => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $payload['message_count'] = 1;
            DB::table('notifications')->insert([
                'id'              => (string) Str::uuid(),
                'type'            => 'App\\Notifications\\ChatMessageNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id'   => $recipientId,
                'data'            => json_encode($payload),
                'read_at'         => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        $firebase->sendToUser($recipient, $senderName, $body, [
            'screen'          => 'chat',
            'conversation_id' => (string) $conversation->id,
            'thread_id'       => 'chat-' . $conversation->id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendChatMessageNotificationJob failed for message {$this->messageId}: " . $exception->getMessage());
    }
}
