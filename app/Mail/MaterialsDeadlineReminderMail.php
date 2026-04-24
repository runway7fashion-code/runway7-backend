<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MaterialsDeadlineReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Stage of the reminder:
     *   'early'     → 30 days before deadline
     *   'upcoming'  → 7 days before
     *   'soon'      → 3 days before
     *   'tomorrow'  → 1 day before
     *   'today'     → day of the deadline
     *   'overdue'   → day after the deadline (or later)
     */
    public function __construct(
        public User $designer,
        public string $eventName,
        public string $deadlineDate,
        public int $daysRemaining,
        public int $pendingCount,
        public string $stage,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->stage) {
            'early'    => "Reminder: materials deadline for {$this->eventName}",
            'upcoming' => "1 week left: upload your materials for {$this->eventName}",
            'soon'     => "Only 3 days left to upload your materials",
            'tomorrow' => "Tomorrow is your deadline to upload your materials",
            'today'    => "Today is the deadline to upload your materials",
            'overdue'  => "Your materials deadline has passed",
            default    => "Materials deadline reminder — {$this->eventName}",
        };

        return new Envelope(
            from: new Address('operations@runway7fashion.com', 'Runway 7 Operations'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.materials-deadline-reminder',
        );
    }
}
