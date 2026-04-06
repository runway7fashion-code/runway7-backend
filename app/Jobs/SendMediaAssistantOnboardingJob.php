<?php

namespace App\Jobs;

use App\Mail\MediaAssistantOnboardingMail;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMediaAssistantOnboardingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $assistantUserId,
        public ?string $mediaName = null,
        public ?string $eventName = null,
        public ?int $logId = null,
    ) {}

    public function handle(): void
    {
        $assistant = User::find($this->assistantUserId);
        if (!$assistant) return;

        Mail::to($assistant->email, "{$assistant->first_name} {$assistant->last_name}")
            ->send(new MediaAssistantOnboardingMail(
                assistant: $assistant,
                mediaName: $this->mediaName,
                eventName: $this->eventName,
            ));

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)
                ->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("SendMediaAssistantOnboardingJob failed: " . $exception->getMessage());
        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)
                ->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }
}
