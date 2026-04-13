<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendBulkUserNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public string $titleTemplate,
        public string $bodyTemplate,
        public int $senderId,
        public array $data = [],
    ) {}

    public function handle(FirebaseNotificationService $firebase, SmsService $smsService): void
    {
        $user = User::find($this->userId);
        if (!$user) return;

        // Replace variables per user
        $title = $smsService->replaceVariables($this->titleTemplate, $user);
        $body = $smsService->replaceVariables($this->bodyTemplate, $user);

        // 1) Store in the notifications table so the mobile app can list them
        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\OutreachNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id'   => $this->userId,
            'data'            => json_encode([
                'title'   => $title,
                'body'    => $body,
                'screen'  => $this->data['screen'] ?? null,
                'sent_by' => $this->senderId,
            ]),
            'read_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2) Send push notification via FCM (optional if user has the app)
        $sent = $firebase->sendToUser($user, $title, $body, $this->data);

        // Log in communication_logs for tracking.
        // Even if no device tokens, the notification was stored and will be visible in the app.
        CommunicationLog::create([
            'user_id'       => $this->userId,
            'sent_by'       => $this->senderId,
            'type'          => 'notification',
            'channel'       => 'communications_outreach',
            'message'       => $title . "\n" . $body,
            'status'        => 'sent',
            'error_message' => $sent === 0 ? 'Stored in-app only (no active device tokens for push)' : null,
            'sent_at'       => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendBulkUserNotificationJob failed for user {$this->userId}: " . $exception->getMessage());

        CommunicationLog::create([
            'user_id'       => $this->userId,
            'sent_by'       => $this->senderId,
            'type'          => 'notification',
            'channel'       => 'communications_outreach',
            'status'        => 'failed',
            'error_message' => substr($exception->getMessage(), 0, 1000),
        ]);
    }
}
