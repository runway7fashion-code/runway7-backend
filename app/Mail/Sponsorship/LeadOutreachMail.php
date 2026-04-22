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
        $mail = $this->from($this->sender->email, "{$this->sender->first_name} {$this->sender->last_name}")
            ->replyTo($this->sender->email, "{$this->sender->first_name} {$this->sender->last_name}")
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
