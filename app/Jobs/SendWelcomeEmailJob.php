<?php

namespace App\Jobs;

use App\Mail\WelcomeModelMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Reintentos si falla (ej: SES timeout) */
    public int $tries = 3;

    /** Espera entre reintentos: 60s, 300s, 600s */
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public ?string $eventName = null,
        public ?string $castingTime = null,
        public ?string $castingDate = null,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) return;

        // Doble chequeo: si ya se envió en otro job, saltar
        if ($user->welcome_email_sent_at) return;

        Mail::to($user->email, "{$user->first_name} {$user->last_name}")
            ->send(new WelcomeModelMail(
                model:       $user,
                eventName:   $this->eventName,
                castingTime: $this->castingTime,
                castingDate: $this->castingDate,
            ));

        $user->update(['welcome_email_sent_at' => now()]);
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("SendWelcomeEmailJob failed for user {$this->userId}: " . $exception->getMessage());
    }
}
