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
        $lead = Lead::with(['primaryEmail', 'emails', 'company:id,name'])->find($this->leadId);
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

        // Sustituir merge tags ({{first_name}}, {{company}}, etc.) con datos del lead.
        $personalizedSubject = $this->renderTemplate($this->subjectLine, $lead, $sender, $primary->email);
        $personalizedBody    = $this->renderTemplate($this->bodyText,    $lead, $sender, $primary->email);

        try {
            $pending = Mail::to($primary->email, "{$lead->first_name} {$lead->last_name}");
            if (!empty($ccEmails)) {
                $pending->cc($ccEmails);
            }
            $sentMessage = $pending->send(new LeadOutreachMail(
                sender: $sender,
                subjectLine: $personalizedSubject,
                bodyText: $personalizedBody,
                fileAttachments: $this->attachments,
            ));

            // ── Persistir el activity INMEDIATAMENTE ─────────────────────────────
            // Mailgun puede disparar el webhook de bounce/complained en menos de 1 segundo
            // (sobre todo para direcciones suprimidas). Si hacemos cualquier trabajo
            // intermedio (IMAP APPEND, etc.) antes de guardar el activity con su
            // mailgun_message_id, el webhook llega antes que el INSERT y el lookup falla.
            $messageId = null;
            if ($sentMessage) {
                try {
                    $messageId = trim((string) $sentMessage->getSymfonySentMessage()->getMessageId(), '<> ');
                    if ($messageId === '') $messageId = null;
                } catch (\Throwable $e) {
                    Log::warning("Could not extract Message-Id: " . $e->getMessage());
                }
            }

            $activity = LeadActivity::create([
                'lead_id'             => $lead->id,
                'created_by_user_id'  => $sender->id,
                'assigned_to_user_id' => $sender->id,
                'type'                => 'email',
                'title'               => $personalizedSubject,
                'description'         => $personalizedBody,
                'completed_at'        => now(),
                'status'              => 'completed',
                'is_contract'         => $this->isContract,
                'mailgun_message_id'  => $messageId,
            ]);

            $lead->update([
                'last_email_sent_at' => now(),
                'last_email_status'  => 'sent',
                'last_contacted_at'  => now(),
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

            // ── Best-effort: copiar el MIME a la carpeta Sent de Zoho del remitente ─
            // Hacemos esto DESPUÉS de crear el activity (puede tardar 1-3 seg de IMAP).
            if ($sentMessage) {
                try {
                    $raw = $sentMessage->getSymfonySentMessage()->toString();
                    app(ZohoSentFolderAppender::class)->append($sender, $raw);
                } catch (\Throwable $e) {
                    Log::warning("[Zoho APPEND] Could not build MIME for sender {$sender->email}: " . $e->getMessage());
                }
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

    /**
     * Sustituye placeholders {{var}} con los datos del lead/sender.
     * Tags soportados: first_name, last_name, full_name, company, email, advisor_name.
     */
    private function renderTemplate(string $template, Lead $lead, User $sender, string $recipientEmail): string
    {
        $companyName = $lead->company?->name ?? '';
        $vars = [
            '{{first_name}}'   => $lead->first_name ?? '',
            '{{last_name}}'    => $lead->last_name ?? '',
            '{{full_name}}'    => trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? '')),
            '{{company}}'      => $companyName,
            '{{email}}'        => $recipientEmail,
            '{{advisor_name}}' => trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')),
        ];
        return str_replace(array_keys($vars), array_values($vars), $template);
    }
}
