<?php

namespace App\Jobs;

use App\Mail\MediaOnboardingMail;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMediaOnboardingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public ?int $logId = null,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) return;

        $user->load(['eventsAsMedia.eventDays']);

        $events = $user->eventsAsMedia
            ->filter(fn($event) => $event->pivot->status !== 'rejected')
            ->map(function ($event) {
                $days = $event->eventDays->map(fn($d) => [
                    'label' => $d->label,
                    'date'  => $d->date?->format('Y-m-d'),
                ])->toArray();
                return [
                    'name' => $event->name,
                    'days' => $days,
                ];
            })->values()->toArray();

        if (empty($events)) return;

        Mail::to($user->email, "{$user->first_name} {$user->last_name}")
            ->send(new MediaOnboardingMail(media: $user, events: $events));

        $user->update(['welcome_email_sent_at' => now()]);

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)
                ->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("SendMediaOnboardingJob failed for user {$this->userId}: " . $exception->getMessage());
        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)
                ->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }
}
