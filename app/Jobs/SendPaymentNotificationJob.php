<?php

namespace App\Jobs;

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

class SendPaymentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $recipientId,
        public string $title,
        public string $body,
        public int $eventId,
        public string $type,
        public ?int $installmentId = null,
        public ?int $senderId = null,
    ) {}

    public function handle(FirebaseNotificationService $firebase): void
    {
        $recipient = User::find($this->recipientId);
        if (!$recipient) return;

        $payload = [
            'screen'   => 'event_payments',
            'event_id' => $this->eventId,
            'type'     => $this->type,
        ];
        if ($this->installmentId !== null) {
            $payload['installment_id'] = $this->installmentId;
        }

        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\PaymentNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id'   => $this->recipientId,
            'data'            => json_encode([
                'title'   => $this->title,
                'body'    => $this->body,
                'sent_by' => $this->senderId,
            ] + $payload),
            'read_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firebase->sendToUser($recipient, $this->title, $this->body, array_map(
            fn ($v) => is_int($v) ? (string) $v : $v,
            $payload,
        ));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendPaymentNotificationJob failed for user {$this->recipientId}: " . $exception->getMessage());
    }
}
