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
        public ?string $eventName = null,
        public ?int $sentBy = null,
        public ?int $logId = null,
    ) {}

    public function handle(): void
    {
        $user = User::with('volunteerSchedules.eventDay')->find($this->userId);

        if (!$user) return;

        // Obtener schedules para el email
        $schedules = $user->volunteerSchedules
            ->sortBy(fn ($s) => $s->eventDay?->date)
            ->map(function ($s) {
                $date = $s->eventDay?->date;
                $dayLabel = $date instanceof \Carbon\Carbon
                    ? $date->format('l, M d')
                    : ($date ? \Carbon\Carbon::parse($date)->format('l, M d') : null);

                // Obtener area del evento (event_staff pivot)
                $area = \Illuminate\Support\Facades\DB::table('event_staff')
                    ->where('user_id', $s->user_id)
                    ->where('event_id', $s->event_id)
                    ->value('area');

                return [
                    'day'   => $dayLabel,
                    'start' => $s->start_time,
                    'end'   => $s->end_time,
                    'area'  => $area,
                ];
            })->values()->toArray();

        Mail::to($user->email, "{$user->first_name} {$user->last_name}")
            ->send(new VolunteerOnboardingMail(
                volunteer: $user,
                eventName: $this->eventName,
                schedules: $schedules,
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
