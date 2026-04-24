<?php

namespace App\Mail\Sponsorship;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadOutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $sender,
        public string $subjectLine,
        public string $bodyText,
        public array $attachmentPaths = [],
    ) {}

    public function build()
    {
        $mail = $this->from($this->sender->email, 'Runway 7 Fashion')
            ->replyTo($this->sender->email, 'Runway 7 Fashion')
            ->subject($this->subjectLine)
            ->view('emails.sponsorship.lead-outreach', [
                'sender' => $this->sender,
                'bodyText' => $this->bodyText,
            ]);

        foreach ($this->attachmentPaths as $path) {
            $full = storage_path('app/' . $path);
            if (is_file($full)) {
                $mail->attach($full, ['as' => basename($path)]);
            }
        }

        return $mail;
    }
}
