<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DesignerStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public User $designer,
        public string $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $profile = $this->designer->designerProfile;
        $label = $this->newStatus === 'active' ? 'Activo' : 'Pendiente';

        return [
            'type'        => 'designer_status_changed',
            'title'       => "Diseñador ahora en estado {$label}",
            'message'     => "{$this->designer->full_name}" . ($profile?->brand_name ? " ({$profile->brand_name})" : '') . " cambió a estado {$label}.",
            'designer_id' => $this->designer->id,
            'new_status'  => $this->newStatus,
        ];
    }
}
