<?php

namespace App\Jobs;

use App\Mail\AssistantOnboardingMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAssistantOnboardingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $assistantUserId,
        public ?string $designerName = null,
        public ?string $brandName = null,
        public ?string $eventName = null,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->assistantUserId);

        if (!$user) return;

        Mail::to($user->email, $user->full_name)
            ->send(new AssistantOnboardingMail(
                assistant:    $user,
                designerName: $this->designerName,
                brandName:    $this->brandName,
                eventName:    $this->eventName,
            ));
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error(
            "SendAssistantOnboardingJob failed for user {$this->assistantUserId}: " . $exception->getMessage()
        );
    }
}
