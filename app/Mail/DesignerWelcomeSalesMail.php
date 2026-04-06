<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DesignerWelcomeSalesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $designer,
        public ?string $brandName = null,
        public ?string $eventName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('designers@runway7fashion.com', 'Runway 7 Designers'),
            subject: 'Welcome to Runway 7 — You\'re Registered!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.designer-welcome-sales',
        );
    }
}
