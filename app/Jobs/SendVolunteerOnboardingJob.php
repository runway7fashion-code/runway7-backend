<?php

namespace App\Jobs;

use App\Mail\VolunteerOnboardingMail;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendVolunteerOnboardingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public ?int $sentBy = null,
        public ?int $logId = null,
    ) {}

    public function handle(): void
    {
        $user = User::with([
            'volunteerSchedules.eventDay',
            'eventsAsStaff' => fn ($q) => $q->wherePivot('status', 'assigned'),
        ])->find($this->userId);

        if (!$user) return;

        // Construir array de eventos con sus horarios agrupados
        $events = $user->eventsAsStaff->map(function ($event) use ($user) {
            $area = $event->pivot->area;

            $schedules = $user->volunteerSchedules
                ->where('event_id', $event->id)
                ->sortBy(fn ($s) => $s->eventDay?->date)
                ->map(function ($s) use ($area) {
                    $date = $s->eventDay?->date;
                    $dayLabel = $date instanceof \Carbon\Carbon
                        ? $date->format('l, M d')
                        : ($date ? \Carbon\Carbon::parse($date)->format('l, M d') : null);

                    return [
                        'day'   => $dayLabel,
                        'start' => $s->start_time,
                        'end'   => $s->end_time,
                        'area'  => $area,
                    ];
                })->values()->toArray();

            return [
                'name'      => $event->name,
                'area'      => $area,
                'schedules' => $schedules,
            ];
        })->values()->toArray();

        Mail::to($user->email, "{$user->first_name} {$user->last_name}")
            ->send(new VolunteerOnboardingMail(
                volunteer: $user,
                events: $events,
            ));

        $user->update(['welcome_email_sent_at' => now()]);

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)->update([
                'status' => 'sent', 'error_message' => null, 'sent_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("SendVolunteerOnboardingJob failed for user {$this->userId}: " . $exception->getMessage());

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)->update([
                'status' => 'failed', 'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
