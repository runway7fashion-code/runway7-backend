<?php

namespace App\Jobs;

use App\Mail\UserOutreachMail;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendBulkUserEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public string $subject,
        public string $body,
        public int $senderId,
        public string $senderName,
        public string $senderEmail,
        public array $attachmentPaths = [],
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user || !$user->email) return;

        $recipientName = "{$user->first_name} {$user->last_name}";

        $mailable = new UserOutreachMail(
            emailSubject: $this->subject,
            emailBody: $this->body,
            senderName: $this->senderName,
            senderEmail: $this->senderEmail,
            recipientName: $recipientName,
        );

        // Attach files (don't delete since other jobs in the same batch share them)
        foreach ($this->attachmentPaths as $path) {
            if (Storage::disk('local')->exists($path)) {
                $mailable->attach(Storage::disk('local')->path($path), [
                    'as' => basename($path),
                ]);
            }
        }

        Mail::to($user->email, $recipientName)
            ->bcc($this->senderEmail)
            ->send($mailable);

        // Log communication
        CommunicationLog::create([
            'user_id'       => $this->userId,
            'sent_by'       => $this->senderId,
            'type'          => 'email',
            'channel'       => 'communications_outreach',
            'status'        => 'sent',
            'sent_at'       => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendBulkUserEmailJob failed for user {$this->userId}: " . $exception->getMessage());

        CommunicationLog::create([
            'user_id'       => $this->userId,
            'sent_by'       => $this->senderId,
            'type'          => 'email',
            'channel'       => 'communications_outreach',
            'status'        => 'failed',
            'error_message' => substr($exception->getMessage(), 0, 1000),
        ]);
    }
}
