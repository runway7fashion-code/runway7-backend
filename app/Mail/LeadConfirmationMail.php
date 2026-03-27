<?php

namespace App\Mail;

use App\Models\DesignerLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailbox;
use Illuminate\Queue\SerializesModels;

class LeadConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DesignerLead $lead) {}

    public function build()
    {
        return $this->from('designers@runway7fashion.com', 'Runway 7 Fashion')
            ->subject('Thank you for your interest in Runway 7 Fashion Week')
            ->view('emails.lead-confirmation');
    }
}
