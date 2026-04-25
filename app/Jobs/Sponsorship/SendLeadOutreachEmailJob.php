<?php

namespace App\Jobs\Sponsorship;

use App\Mail\Sponsorship\LeadOutreachMail;
use App\Models\Sponsorship\Lead;
use App\Models\Sponsorship\LeadActivity;
use App\Models\Sponsorship\LeadActivityFile;
use App\Models\User;
use App\Services\Sponsorship\ZohoSentFolderAppender;
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

    /**
     * @param array<int, array{path:string,name:string,mime:?string,size:?int}> $attachments
     */
    public function __construct(
        public int $leadId,
        public int $senderUserId,
        public string $subjectLine,
        public string $bodyText,
        public bool $isContract = false,
        public array $attachments = [],
    ) {}

    public function handle(): void
    {
        $lead = Lead::with(['primaryEmail', 'emails'])->find($this->leadId);
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

        // CC: cualquier email secundario registrado para este lead.
        $ccEmails = $lead->emails
            ->where('is_primary', false)
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        try {
            $pending = Mail::to($primary->email, "{$lead->first_name} {$lead->last_name}");
            if (!empty($ccEmails)) {
                $pending->cc($ccEmails);
            }
            $sentMessage = $pending->send(new LeadOutreachMail(
                sender: $sender,
                subjectLine: $this->subjectLine,
                bodyText: $this->bodyText,
                fileAttachments: $this->attachments,
            ));

            // Best-effort: copiar el MIME a la carpeta Sent de Zoho del remitente,
            // para que aparezca como enviado en su bandeja. No interrumpe el flujo.
            if ($sentMessage) {
                try {
                    $raw = $sentMessage->getSymfonySentMessage()->toString();
                    app(ZohoSentFolderAppender::class)->append($sender, $raw);
                } catch (\Throwable $e) {
                    Log::warning("[Zoho APPEND] Could not build MIME for sender {$sender->email}: " . $e->getMessage());
                }
            }

            $lead->update([
                'last_email_sent_at' => now(),
                'last_email_status'  => 'sent',
                'last_contacted_at'  => now(),
            ]);

            // Crear actividad en el timeline con status completed
            // NOTE: cuando se reactive Mailgun webhook, capturar aquí el Message-Id vía
            // $sentMessage->getSymfonySentMessage()->getMessageId() y persistirlo en
            // 'mailgun_message_id' (requiere migration add_mailgun_tracking_to_sponsorship_lead_activities).
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

            // Persistir los adjuntos en el timeline para que sean previsualizables/descargables.
            foreach ($this->attachments as $att) {
                LeadActivityFile::create([
                    'activity_id' => $activity->id,
                    'file_path'   => $att['path'],
                    'file_name'   => $att['name'],
                    'mime_type'   => $att['mime'] ?? null,
                    'size'        => $att['size'] ?? null,
                ]);
            }

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
