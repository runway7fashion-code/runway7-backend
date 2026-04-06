<?php

namespace App\Notifications;

use App\Models\DesignerLead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewDesignerLead extends Notification
{
    use Queueable;

    public function __construct(public DesignerLead $lead) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'    => 'new_designer_lead',
            'title'   => 'Nuevo Prospecto',
            'message' => "{$this->lead->full_name} ({$this->lead->company_name}) se registró como prospecto",
            'lead_id' => $this->lead->id,
            'url'     => "/admin/sales/leads/{$this->lead->id}",
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new \Illuminate\Notifications\Messages\BroadcastMessage($this->toArray($notifiable));
    }
}
