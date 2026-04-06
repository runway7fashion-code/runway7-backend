<?php

namespace App\Jobs;

use App\Mail\DesignerWelcomeSalesMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDesignerWelcomeSalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public ?string $brandName = null,
        public ?string $eventName = null,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) return;

        Mail::to($user->email, "{$user->first_name} {$user->last_name}")
            ->send(new DesignerWelcomeSalesMail(
                designer:  $user,
                brandName: $this->brandName,
                eventName: $this->eventName,
            ));
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("SendDesignerWelcomeSalesJob failed for user {$this->userId}: " . $exception->getMessage());
    }
}
