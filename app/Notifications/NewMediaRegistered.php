<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class NewMediaRegistered extends Notification
{
    public function __construct(
        public User $media,
        public string $eventName,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'       => 'new_media_registered',
            'title'      => 'Nuevo registro de media',
            'message'    => "{$this->media->first_name} {$this->media->last_name} se registró como media para {$this->eventName}.",
            'user_id'    => $this->media->id,
            'event_name' => $this->eventName,
        ];
    }
}
