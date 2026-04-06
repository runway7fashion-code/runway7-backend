<?php

namespace App\Jobs;

use App\Mail\DesignerOnboardingMail;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDesignerOnboardingJob implements ShouldQueue
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
        $designer = User::with([
            'eventsAsDesigner',
            'designedShows.eventDay',
        ])->find($this->userId);

        if (!$designer) return;

        // Construir array de eventos con sus shows agrupados
        $events = $designer->eventsAsDesigner->map(function ($event) use ($designer) {
            $shows = $designer->designedShows
                ->filter(fn ($show) => $show->eventDay
                    && $show->pivot->status !== 'cancelled'
                    && $show->eventDay->event_id == $event->id)
                ->sortBy([
                    fn ($a, $b) => $a->eventDay->date <=> $b->eventDay->date,
                    fn ($a, $b) => $a->scheduled_time <=> $b->scheduled_time,
                ])
                ->map(fn ($show) => [
                    'day_label'      => $show->eventDay->label,
                    'day_date'       => $show->eventDay->date instanceof \Carbon\Carbon
                        ? $show->eventDay->date->format('Y-m-d')
                        : $show->eventDay->date,
                    'scheduled_time' => $show->scheduled_time,
                    'show_name'      => $show->name,
                ])->values()->toArray();

            return [
                'name'  => $event->name,
                'shows' => $shows,
            ];
        })->values()->toArray();

        Mail::to($designer->email, "{$designer->first_name} {$designer->last_name}")
            ->send(new DesignerOnboardingMail(
                designer: $designer,
                events:   $events,
            ));

        $designer->update(['welcome_email_sent_at' => now()]);

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)->update([
                'status' => 'sent', 'error_message' => null, 'sent_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("SendDesignerOnboardingJob failed for user {$this->userId}: " . $exception->getMessage());

        if ($this->logId) {
            CommunicationLog::where('id', $this->logId)->update([
                'status' => 'failed', 'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
