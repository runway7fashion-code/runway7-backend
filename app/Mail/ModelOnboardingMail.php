<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ModelOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $model,
        public array $events = [],
        public ?string $tag = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('models@runway7fashion.com', 'Runway 7 Models'),
            subject: 'Your Casting Details — Runway 7',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.model-onboarding',
        );
    }
}
