<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeModelMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $model,
        public ?string $eventName = null,
        public ?string $castingTime = null,
        public ?string $castingDate = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Runway 7! — Your Event Access',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.model-welcome',
        );
    }
}
