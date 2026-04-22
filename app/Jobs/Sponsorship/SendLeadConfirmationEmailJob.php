<?php

namespace App\Jobs\Sponsorship;

use App\Mail\Sponsorship\LeadConfirmationMail;
use App\Models\Sponsorship\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLeadConfirmationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $leadId) {}

    public function handle(): void
    {
        $lead = Lead::with('company:id,name')->find($this->leadId);
        if (!$lead) {
            return;
        }

        $primary = $lead->emails()->where('is_primary', true)->first();
        if (!$primary) {
            Log::warning("Sponsorship lead {$lead->id} has no primary email.");
            return;
        }

        try {
            Mail::to($primary->email, "{$lead->first_name} {$lead->last_name}")
                ->send(new LeadConfirmationMail($lead));

            $lead->update([
                'last_email_sent_at' => now(),
                'last_email_status'  => 'sent',
            ]);
        } catch (\Throwable $e) {
            Log::warning("Sponsorship lead confirmation email failed (lead {$lead->id}): " . $e->getMessage());
            $lead->update([
                'last_email_sent_at' => now(),
                'last_email_status'  => 'failed',
            ]);
            throw $e; // allow retry
        }
    }
}
