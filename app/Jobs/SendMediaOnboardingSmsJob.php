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

class SendMediaOnboardingSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public ?int $sentBy = null,
        public ?int $logId = null,
    ) {}

    public function handle(TwilioService $twilio): void
    {
        $user = User::with([
            'eventsAsMedia' => fn($q) => $q->wherePivot('status', 'assigned'),
        ])->find($this->userId);

        if (!$user || !$user->phone) return;

        if (!str_starts_with($user->phone, '+')) {
            Log::warning("SendMediaOnboardingSmsJob skipped for user {$this->userId}: phone missing country code");
            return;
        }

        $name = $user->first_name;
        $eventNames = $user->eventsAsMedia->pluck('name')->toArray();
        $event = count($eventNames) > 0 ? ' for ' . implode(', ', $eventNames) : '';

        $appStore  = config('services.app_stores.apple');
        $playStore = config('services.app_stores.google');

        $message = "Hi {$name}! You've been approved for Runway 7 media coverage{$event}. "
            . "Download our app to access your media pass & event details.\n\n"
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
        Log::error("SendMediaOnboardingSmsJob failed for user {$this->userId}: " . $exception->getMessage());
        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)->update([
                'status' => 'failed', 'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
