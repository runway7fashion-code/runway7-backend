<?php

namespace App\Mail\Sponsorship;

use App\Models\Sponsorship\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function build()
    {
        return $this->from('partnerships@runway7fashion.com', 'Runway 7 Partnerships')
            ->subject('Thank you for your interest in Runway 7 Fashion Week')
            ->view('emails.sponsorship.lead-confirmation');
    }
}
