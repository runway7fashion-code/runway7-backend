<?php

namespace App\Events\Sponsorship;

use App\Models\Sponsorship\LeadActivity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado desde el webhook de Mailgun cuando una actividad de email cambia
 * su estado de entrega (delivered / bounced / complained / rejected / temporary_fail).
 *
 * Se transmite por el canal privado `sponsorship-lead.{lead_id}`, al que se
 * suscribe la página de detalle del lead para refrescar el badge sin reload.
 */
class LeadActivityDeliveryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LeadActivity $activity) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sponsorship-lead.' . $this->activity->lead_id)];
    }

    public function broadcastAs(): string
    {
        return 'LeadActivityDeliveryUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'                 => $this->activity->id,
            'lead_id'            => $this->activity->lead_id,
            'status'             => $this->activity->status,
            'delivery_status'    => $this->activity->delivery_status,
            'delivery_error'     => $this->activity->delivery_error,
            'delivered_at'       => optional($this->activity->delivered_at)->toIso8601String(),
            'mailgun_message_id' => $this->activity->mailgun_message_id,
        ];
    }
}
