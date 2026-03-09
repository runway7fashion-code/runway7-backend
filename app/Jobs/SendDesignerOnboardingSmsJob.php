<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDesignerOnboardingSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public ?string $eventName = null,
        public ?int $sentBy = null,
        public ?int $logId = null,
    ) {}

    public function handle(TwilioService $twilio): void
    {
        $user = User::find($this->userId);

        if (!$user || !$user->phone) return;

        $phone = $user->phone;
        if (!str_starts_with($phone, '+')) {
            Log::warning("SendDesignerOnboardingSmsJob skipped for user {$this->userId}: phone '{$phone}' missing country code (must start with +)");
            return;
        }

        $name = $user->first_name;
        $event = $this->eventName ? " for {$this->eventName}" : '';

        $appStore  = config('services.app_stores.apple');
        $playStore = config('services.app_stores.google');

        $message = "Hi {$name}! Welcome to Runway 7{$event}. "
            . "Download our app to manage your shows, schedules & credentials.\n\n"
            . "Email: {$user->email}\n"
            . "Password: runway7\n\n"
            . "App Store: {$appStore}\n\n"
            . "Google Play: {$playStore}";

        $twilio->send($user->phone, $message);

        $user->update(['sms_sent_at' => now()]);

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)->update([
                'status' => 'sent', 'error_message' => null, 'sent_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendDesignerOnboardingSmsJob failed for user {$this->userId}: " . $exception->getMessage());

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)->update([
                'status' => 'failed', 'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
