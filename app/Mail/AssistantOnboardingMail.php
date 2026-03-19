<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssistantOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $assistant,
        public ?string $designerName = null,
        public ?string $brandName = null,
        public ?string $eventName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('operations@runway7fashion.com', 'Runway 7'),
            subject: 'Welcome to Runway 7! — Assistant Access',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assistant-onboarding',
        );
    }
}
