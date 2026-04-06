<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewModelRegistered extends Notification
{
    use Queueable;

    public function __construct(
        public User $model,
        public string $eventName,
        public bool $fastTrack = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'new_model_registered',
            'title'      => 'Nueva modelo registrada',
            'message'    => "{$this->model->full_name} se registró para {$this->eventName}" . ($this->fastTrack ? ' (Shopify ✓)' : '.'),
            'model_id'   => $this->model->id,
            'event_name' => $this->eventName,
            'fast_track' => $this->fastTrack,
        ];
    }
}
