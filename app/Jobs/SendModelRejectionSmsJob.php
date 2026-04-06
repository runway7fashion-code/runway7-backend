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

class SendModelRejectionSmsJob implements ShouldQueue
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
        $user = User::find($this->userId);

        if (!$user || !$user->phone) return;

        if (!str_starts_with($user->phone, '+')) {
            Log::warning("SendModelRejectionSmsJob skipped for user {$this->userId}: phone missing country code");
            return;
        }

        $message = "Hi {$user->first_name}, thank you for applying to Runway 7. "
            . "Unfortunately, you were not selected for the main casting. "
            . "But your chance isn't over! Purchase \$100+ merch at https://runway7.co/modelcasting "
            . "to join our exclusive Merch Casting.";

        $twilio->send($user->phone, $message);

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)->update([
                'status' => 'sent', 'error_message' => null, 'sent_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendModelRejectionSmsJob failed for user {$this->userId}: " . $exception->getMessage());

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)->update([
                'status' => 'failed', 'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
