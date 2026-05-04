<?php

namespace App\Jobs;

use App\Models\Show;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reminder push to a model whose casting invitation is about to expire.
 * The job is scheduled with delay() at invitation creation time. On handle(),
 * it re-checks the invitation status: if the model already responded, the job
 * is a no-op.
 */
class ExpireInvitationReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $showModelId,
        public string $window, // '1h' | '30m' | '5m'
    ) {}

    public function handle(FirebaseNotificationService $firebase): void
    {
        $row = DB::table('show_model')->where('id', $this->showModelId)->first();
        if (!$row || $row->status !== 'requested') return;

        $model = User::find($row->model_id);
        $show = Show::find($row->show_id);
        $designer = User::find($row->designer_id);
        if (!$model || !$show || !$designer) return;

        $brandName = $designer->designerProfile?->brand_name;
        $displayName = $brandName ?: trim($designer->first_name . ' ' . $designer->last_name);

        $label = match ($this->window) {
            '1h'  => '1 hour',
            '30m' => '30 minutes',
            '5m'  => '5 minutes',
            default => 'soon',
        };

        $title = "Invitation expires in {$label}";
        $body = "Respond to {$displayName}'s invitation for {$show->name} before it expires.";

        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\CastingNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id'   => $model->id,
            'data'            => json_encode([
                'title'   => $title,
                'body'    => $body,
                'screen'  => 'shows',
                'show_id' => $show->id,
                'sent_by' => $designer->id,
                'type'    => "invitation_expires_{$this->window}",
            ]),
            'read_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firebase->sendToUser($model, $title, $body, [
            'screen'  => 'shows',
            'show_id' => (string) $show->id,
            'type'    => "invitation_expires_{$this->window}",
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ExpireInvitationReminderJob failed for show_model {$this->showModelId}: " . $exception->getMessage());
    }
}
