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

class SendCastingNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $recipientId,
        public string $title,
        public string $body,
        public int $showId,
        public ?int $senderId = null,
    ) {}

    public function handle(FirebaseNotificationService $firebase): void
    {
        $recipient = User::find($this->recipientId);
        if (!$recipient) return;

        // Store in-app notification
        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\CastingNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id'   => $this->recipientId,
            'data'            => json_encode([
                'title'     => $this->title,
                'body'      => $this->body,
                'screen'    => 'shows',
                'show_id'   => $this->showId,
                'sent_by'   => $this->senderId,
            ]),
            'read_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send push notification
        $firebase->sendToUser($recipient, $this->title, $this->body, [
            'screen'  => 'shows',
            'show_id' => (string) $this->showId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendCastingNotificationJob failed for user {$this->recipientId}: " . $exception->getMessage());
    }
}
