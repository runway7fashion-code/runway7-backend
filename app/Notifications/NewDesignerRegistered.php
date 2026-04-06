<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewDesignerRegistered extends Notification
{
    use Queueable;

    public function __construct(
        public User $designer,
        public User $salesRep,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $profile = $this->designer->designerProfile;

        return [
            'type'       => 'new_designer_registered',
            'title'      => 'Nuevo diseñador registrado',
            'message'    => "{$this->salesRep->full_name} registró al diseñador {$this->designer->full_name}" . ($profile?->brand_name ? " ({$profile->brand_name})" : ''),
            'designer_id'=> $this->designer->id,
            'sales_rep'  => $this->salesRep->full_name,
        ];
    }
}
