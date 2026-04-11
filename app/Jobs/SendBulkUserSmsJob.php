<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\SmsService;
use App\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkUserSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public string $messageTemplate,
        public int $senderId,
    ) {}

    public function handle(TwilioService $twilio, SmsService $smsService): void
    {
        $user = User::find($this->userId);
        if (!$user) return;

        $phone = $smsService->normalizePhone($user->phone);
        if (!$phone) {
            $this->logFailure('Invalid phone format');
            return;
        }

        // Replace variables and append signature
        $message = $smsService->replaceVariables($this->messageTemplate, $user);
        $message = $smsService->appendSignature($message);

        $segments = $smsService->calculateSegments($message);
        $cost = round($segments * SmsService::PRICE_PER_SEGMENT_US, 4);

        try {
            $twilio->send($phone, $message);

            CommunicationLog::create([
                'user_id'  => $this->userId,
                'sent_by'  => $this->senderId,
                'type'     => 'sms',
                'channel'  => 'communications_outreach',
                'message'  => $message,
                'segments' => $segments,
                'cost'     => $cost,
                'status'   => 'sent',
                'sent_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            $this->logFailure($e->getMessage(), $message, $segments, $cost);
            throw $e;
        }
    }

    private function logFailure(string $error, ?string $message = null, ?int $segments = null, ?float $cost = null): void
    {
        CommunicationLog::create([
            'user_id'       => $this->userId,
            'sent_by'       => $this->senderId,
            'type'          => 'sms',
            'channel'       => 'communications_outreach',
            'message'       => $message,
            'segments'      => $segments,
            'cost'          => $cost,
            'status'        => 'failed',
            'error_message' => substr($error, 0, 1000),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendBulkUserSmsJob failed for user {$this->userId}: " . $exception->getMessage());
    }
}
