<?php

namespace App\Mail\Sponsorship;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SponsorOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $sponsor,
        public string $password = 'runway7',
    ) {}

    public function build()
    {
        return $this->from('partnerships@runway7fashion.com', 'Runway 7 Partnerships')
            ->subject('Welcome to Runway 7 — Your Sponsor Account Is Ready')
            ->view('emails.sponsorship.sponsor-onboarding');
    }
}
