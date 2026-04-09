<?php

namespace App\Jobs;

use App\Mail\LeadOutreachMail;
use App\Models\DesignerLead;
use App\Models\LeadActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendLeadEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $leadId,
        public string $subject,
        public string $body,
        public int $senderId,
        public string $senderName,
        public string $senderEmail,
        public array $attachmentPaths = [],
    ) {}

    public function handle(): void
    {
        $lead = DesignerLead::find($this->leadId);
        if (!$lead || !$lead->email) return;

        $recipientName = "{$lead->first_name} {$lead->last_name}";

        $mailable = new LeadOutreachMail(
            emailSubject: $this->subject,
            emailBody: $this->body,
            senderName: $this->senderName,
            senderEmail: $this->senderEmail,
            recipientName: $recipientName,
        );

        // Attach files
        foreach ($this->attachmentPaths as $path) {
            if (Storage::disk('local')->exists($path)) {
                $mailable->attach(Storage::disk('local')->path($path), [
                    'as' => basename($path),
                ]);
            }
        }

        Mail::to($lead->email, $recipientName)
            ->bcc($this->senderEmail)
            ->send($mailable);

        // Log activity
        LeadActivity::create([
            'lead_id'      => $this->leadId,
            'user_id'      => $this->senderId,
            'type'         => 'email',
            'title'        => "Email sent: {$this->subject}",
            'description'  => strip_tags($this->body),
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Update last contacted
        $lead->update(['last_contacted_at' => now()]);

        // Clean up attachments after sending
        foreach ($this->attachmentPaths as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendLeadEmailJob failed for lead {$this->leadId}: " . $exception->getMessage());
    }
}
