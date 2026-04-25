<?php

namespace App\Mail\Sponsorship;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class LeadOutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{path:string,name:string,mime:?string,size:?int}> $fileAttachments
     */
    public function __construct(
        public User $sender,
        public string $subjectLine,
        public string $bodyText,
        public array $fileAttachments = [],
    ) {}

    public function build()
    {
        $mail = $this->from($this->sender->email, 'Runway 7 Fashion')
            ->replyTo($this->sender->email, 'Runway 7 Fashion')
            ->subject($this->subjectLine)
            ->view('emails.sponsorship.lead-outreach', [
                'sender'   => $this->sender,
                'bodyText' => $this->bodyText,
            ]);

        foreach ($this->fileAttachments as $att) {
            $full = Storage::disk('public')->path($att['path']);
            if (is_file($full)) {
                $options = ['as' => $att['name']];
                if (!empty($att['mime'])) {
                    $options['mime'] = $att['mime'];
                }
                $mail->attach($full, $options);
            }
        }

        return $mail;
    }
}
