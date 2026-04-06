<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MediaRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $media,
        public ?string $eventName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('operations@runway7fashion.com', 'Runway 7'),
            subject: 'Thank You for Applying — Runway 7 Media',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.media-registration',
        );
    }
}
