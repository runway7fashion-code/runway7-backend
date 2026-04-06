<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewVolunteerRegistered extends Notification
{
    use Queueable;

    public function __construct(
        public User $volunteer,
        public string $eventName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'new_volunteer_registered',
            'title'      => 'Nuevo voluntario registrado',
            'message'    => "{$this->volunteer->full_name} se registró como voluntario para {$this->eventName}.",
            'user_id'    => $this->volunteer->id,
            'event_name' => $this->eventName,
        ];
    }
}
