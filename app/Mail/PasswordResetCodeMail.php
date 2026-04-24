<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        [$email, $name] = $this->senderFor($this->user->role);

        return new Envelope(
            from: new Address($email, $name),
            subject: 'Your Runway7 password reset code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-code',
            with: [
                'code'      => $this->code,
                'firstName' => $this->user->first_name,
            ],
        );
    }

    /**
     * From-address for each role. Falls back to operations@ for any role
     * without a dedicated inbox (media, internal staff, attendees, etc.).
     */
    private function senderFor(string $role): array
    {
        return match ($role) {
            'model'                  => ['models@runway7fashion.com',       'Runway 7 Models'],
            'designer'               => ['designers@runway7fashion.com',    'Runway 7 Designers'],
            'volunteer'              => ['volunteers@runway7fashion.com',   'Runway 7 Volunteers'],
            'sponsor', 'sponsorship' => ['partnerships@runway7fashion.com', 'Runway 7 Partnerships'],
            default                  => ['operations@runway7fashion.com',   'Runway 7'],
        };
    }
}
