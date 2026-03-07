<?php

namespace App\Jobs;

use App\Mail\DesignerOnboardingMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDesignerOnboardingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public ?string $eventName = null,
        public array $shows = [],
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) return;

        Mail::to($user->email, "{$user->first_name} {$user->last_name}")
            ->send(new DesignerOnboardingMail(
                designer:  $user,
                eventName: $this->eventName,
                shows:     $this->shows,
            ));

        $user->update(['welcome_email_sent_at' => now()]);
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("SendDesignerOnboardingJob failed for user {$this->userId}: " . $exception->getMessage());
    }
}
