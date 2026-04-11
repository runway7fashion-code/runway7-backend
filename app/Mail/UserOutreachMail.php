<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserOutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $emailBody,
        public string $senderName,
        public string $senderEmail,
        public string $recipientName,
    ) {}

    public function build()
    {
        return $this->from($this->senderEmail, $this->senderName)
            ->subject($this->emailSubject)
            ->view('emails.lead-outreach', [
                'body'          => $this->emailBody,
                'recipientName' => $this->recipientName,
                'senderName'    => $this->senderName,
                'senderEmail'   => $this->senderEmail,
            ]);
    }
}
