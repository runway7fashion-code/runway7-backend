<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaterialsDeadlineReminder extends Notification
{
    use Queueable;

    public function __construct(
        public string $eventName,
        public string $deadlineDate,
        public int $daysRemaining,
        public int $pendingCount,
        public string $stage,
        public int $eventId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $title = match ($this->stage) {
            'early'    => 'Materials deadline reminder',
            'upcoming' => '1 week left to upload materials',
            'soon'     => '3 days left to upload materials',
            'tomorrow' => 'Materials deadline is tomorrow',
            'today'    => 'Today is your materials deadline',
            'overdue'  => 'Your materials deadline has passed',
            default    => 'Materials reminder',
        };

        $message = $this->stage === 'overdue'
            ? "Uploads for {$this->eventName} are now blocked. Contact your advisor for an extension."
            : "Deadline {$this->deadlineDate}. You have {$this->pendingCount} pending "
                . ($this->pendingCount === 1 ? 'material' : 'materials') . " for {$this->eventName}.";

        return [
            'type'           => 'materials_deadline_reminder',
            'title'          => $title,
            'message'        => $message,
            'stage'          => $this->stage,
            'event_id'       => $this->eventId,
            'event_name'     => $this->eventName,
            'deadline'       => $this->deadlineDate,
            'days_remaining' => $this->daysRemaining,
            'pending_count'  => $this->pendingCount,
        ];
    }
}
