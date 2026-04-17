<?php

namespace App\Jobs;

use App\Models\DesignerMaterial;
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

class SendMaterialNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $recipientId,
        public string $title,
        public string $body,
        public int $materialId,
        public ?int $senderId = null,
    ) {}

    public function handle(FirebaseNotificationService $firebase): void
    {
        $recipient = User::find($this->recipientId);
        if (!$recipient) return;

        // Store in-app notification
        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\MaterialNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id'   => $this->recipientId,
            'data'            => json_encode([
                'title'       => $this->title,
                'body'        => $this->body,
                'screen'      => 'home',
                'material_id' => $this->materialId,
                'sent_by'     => $this->senderId,
            ]),
            'read_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send push
        $firebase->sendToUser($recipient, $this->title, $this->body, [
            'screen'      => 'home',
            'material_id' => (string) $this->materialId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendMaterialNotificationJob failed for user {$this->recipientId}: " . $exception->getMessage());
    }
}
