<?php

namespace App\Jobs\Sponsorship;

use App\Mail\Sponsorship\LeadOutreachMail;
use App\Models\Sponsorship\Lead;
use App\Models\Sponsorship\LeadActivity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLeadOutreachEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $leadId,
        public int $senderUserId,
        public string $subjectLine,
        public string $bodyText,
        public bool $isContract = false,
        public array $attachmentPaths = [],
    ) {}

    public function handle(): void
    {
        $lead = Lead::with(['primaryEmail'])->find($this->leadId);
        $sender = User::find($this->senderUserId);

        if (!$lead || !$sender) {
            Log::warning("Sponsorship lead outreach: lead {$this->leadId} or sender {$this->senderUserId} missing");
            return;
        }

        $primary = $lead->primaryEmail;
        if (!$primary) {
            Log::warning("Sponsorship lead {$lead->id} has no primary email");
            return;
        }

        try {
            Mail::to($primary->email, "{$lead->first_name} {$lead->last_name}")
                ->send(new LeadOutreachMail(
                    sender: $sender,
                    subjectLine: $this->subjectLine,
                    bodyText: $this->bodyText,
                    attachmentPaths: $this->attachmentPaths,
                ));

            $lead->update([
                'last_email_sent_at' => now(),
                'last_email_status'  => 'sent',
                'last_contacted_at'  => now(),
            ]);

            // Crear actividad en el timeline con status completed
            $activity = LeadActivity::create([
                'lead_id'             => $lead->id,
                'created_by_user_id'  => $sender->id,
                'assigned_to_user_id' => $sender->id,
                'type'                => 'email',
                'title'               => $this->subjectLine,
                'description'         => $this->bodyText,
                'completed_at'        => now(),
                'status'              => 'completed',
                'is_contract'         => $this->isContract,
            ]);

            // Si el email es el contrato → status del lead = contrato
            if ($this->isContract && $lead->status !== 'cerrado') {
                $lead->update(['status' => 'contrato']);
            } elseif ($lead->status === 'nuevo') {
                $lead->update(['status' => 'contactado']);
            }
        } catch (\Throwable $e) {
            Log::warning("Sponsorship lead outreach failed (lead {$lead->id}): " . $e->getMessage());
            $lead->update(['last_email_sent_at' => now(), 'last_email_status' => 'failed']);

            LeadActivity::create([
                'lead_id'             => $lead->id,
                'created_by_user_id'  => $sender->id,
                'assigned_to_user_id' => $sender->id,
                'type'                => 'email',
                'title'               => $this->subjectLine . ' (FAILED)',
                'description'         => "Error: " . $e->getMessage() . "\n\n" . $this->bodyText,
                'status'              => 'not_completed',
            ]);

            throw $e;
        }
    }
}
