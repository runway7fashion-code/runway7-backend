<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VolunteerOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $volunteer,
        public array $events = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Runway 7 — Volunteer Onboarding',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.volunteer-onboarding',
        );
    }
}
