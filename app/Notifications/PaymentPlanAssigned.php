<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentPlanAssigned extends Notification
{
    use Queueable;

    public function __construct(
        public User $designer,
        public User $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $profile = $this->designer->designerProfile;

        return [
            'type'        => 'payment_plan_assigned',
            'title'       => 'Plan de pagos asignado',
            'message'     => "{$this->assignedBy->full_name} asignó un plan de pagos a {$this->designer->full_name}" . ($profile?->brand_name ? " ({$profile->brand_name})" : '') . '.',
            'designer_id' => $this->designer->id,
            'assigned_by' => $this->assignedBy->full_name,
        ];
    }
}
