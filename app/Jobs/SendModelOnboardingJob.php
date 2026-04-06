<?php

namespace App\Jobs;

use App\Mail\ModelOnboardingMail;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendModelOnboardingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public ?int $eventId = null,
        public ?string $tag = null,
        public ?int $logId = null,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) return;

        $user->load(['eventsAsModelWithCasting.eventDays' => fn($q) => $q->where('type', 'casting')]);

        if ($this->eventId) {
            // Evento específico (envío individual con tag)
            $event = $user->eventsAsModelWithCasting->firstWhere('id', $this->eventId);
            if (!$event) return;

            $castingDay = $event->eventDays->first();
            $events = [[
                'name'         => $event->name,
                'casting_date' => $castingDay?->date?->format('Y-m-d'),
                'casting_time' => $event->pivot->casting_time,
            ]];
        } else {
            // Todos los eventos con casting scheduled (envío masivo sin tag)
            $events = $user->eventsAsModelWithCasting
                ->filter(fn($ev) => $ev->pivot->casting_status === 'scheduled')
                ->map(function ($ev) {
                    $castingDay = $ev->eventDays->first();
                    return [
                        'name'         => $ev->name,
                        'casting_date' => $castingDay?->date?->format('Y-m-d'),
                        'casting_time' => $ev->pivot->casting_time,
                    ];
                })->values()->toArray();

            if (empty($events)) return;
        }

        Mail::to($user->email, "{$user->first_name} {$user->last_name}")
            ->send(new ModelOnboardingMail(
                model:  $user,
                events: $events,
                tag:    $this->tag,
            ));

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)
                ->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("SendModelOnboardingJob failed for user {$this->userId}: " . $exception->getMessage());

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)
                ->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }
}
