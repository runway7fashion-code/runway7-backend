<?php

namespace App\Jobs;

use App\Mail\ModelRegistrationMail;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendRegistrationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public ?string $eventName = null,
        public ?int $logId = null,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) return;

        Mail::to($user->email, "{$user->first_name} {$user->last_name}")
            ->send(new ModelRegistrationMail(
                model: $user,
                eventName: $this->eventName,
            ));

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)
                ->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("SendRegistrationEmailJob failed for user {$this->userId}: " . $exception->getMessage());

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)
                ->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }
}
