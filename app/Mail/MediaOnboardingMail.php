<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MediaOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $media,
        public array $events = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('operations@runway7fashion.com', 'Runway 7'),
            subject: 'Welcome to Runway 7 — Media Credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.media-onboarding',
        );
    }
}
