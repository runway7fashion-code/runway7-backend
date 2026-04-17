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

        // Identify the recipient (the other participant)
        $recipientId = $conversation->user_a_id === $message->sender_id
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        $recipient = User::find($recipientId);
        if (!$recipient) return;

        $sender = $message->sender;
        $senderName = trim("{$sender->first_name} {$sender->last_name}");

        // Build notification body (truncate long messages)
        $body = match ($message->type) {
            'image' => '📷 Sent an image',
            'system' => $message->body,
            default => Str::limit($message->body, 100),
        };

        // Store in-app notification
        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\ChatMessageNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id'   => $recipientId,
            'data'            => json_encode([
                'title'           => $senderName,
                'body'            => $body,
                'screen'          => 'chat',
                'conversation_id' => $conversation->id,
                'message_id'      => $message->id,
                'sender_id'       => $sender->id,
            ]),
            'read_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send push notification
        $firebase->sendToUser($recipient, $senderName, $body, [
            'screen'          => 'chat',
            'conversation_id' => (string) $conversation->id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendChatMessageNotificationJob failed for message {$this->messageId}: " . $exception->getMessage());
    }
}
