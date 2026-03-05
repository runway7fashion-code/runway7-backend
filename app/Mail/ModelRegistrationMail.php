<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ModelRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $model,
        public ?string $eventName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address', 'tickets@runway7fashion.com'),
                config('mail.from.name', 'Runway 7')
            ),
            subject: 'Thank You for Applying — Runway 7',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.model-registration',
        );
    }
}
