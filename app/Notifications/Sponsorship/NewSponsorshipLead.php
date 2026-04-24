<?php

namespace App\Notifications\Sponsorship;

use App\Models\Sponsorship\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewSponsorshipLead extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $lead = $this->lead;
        $company = $lead->company?->name ?? 'Unknown company';
        $fullName = trim("{$lead->first_name} {$lead->last_name}");

        return [
            'type'    => 'new_sponsorship_lead',
            'title'   => 'New Sponsorship Lead',
            'message' => "{$fullName} ({$company}) registered as a prospect",
            'lead_id' => $lead->id,
            'url'     => "/admin/sponsorship/leads/{$lead->id}",
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new \Illuminate\Notifications\Messages\BroadcastMessage($this->toArray($notifiable));
    }
}
