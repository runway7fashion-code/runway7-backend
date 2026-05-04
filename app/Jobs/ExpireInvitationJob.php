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
 * Marks a casting invitation as expired when its expires_at is reached, freeing
 * the designer's slot. Notifies both the model and the designer. No-op if the
 * invitation has already been responded to.
 */
class ExpireInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $showModelId) {}

    public function handle(FirebaseNotificationService $firebase): void
    {
        $row = DB::table('show_model')->where('id', $this->showModelId)->first();
        if (!$row || $row->status !== 'requested') return;

        DB::table('show_model')->where('id', $this->showModelId)->update([
            'status'       => 'expired',
            'responded_at' => now(),
            'updated_at'   => now(),
        ]);

        $model = User::find($row->model_id);
        $show = Show::find($row->show_id);
        $designer = User::find($row->designer_id);
        if (!$model || !$show || !$designer) return;

        $brandName = $designer->designerProfile?->brand_name;
        $designerLabel = $brandName ?: trim($designer->first_name . ' ' . $designer->last_name);
        $modelName = trim($model->first_name . ' ' . $model->last_name);

        // Notify the model (informational)
        $modelTitle = 'Invitation expired';
        $modelBody = "Your invitation from {$designerLabel} for {$show->name} expired without a response.";

        // Notify the designer (slot is freed)
        $designerTitle = 'Invitation expired';
        $designerBody = "Your invitation to {$modelName} expired. Your slot is free again.";

        $this->record($model->id, $designer->id, $show->id, $modelTitle, $modelBody);
        $this->record($designer->id, $designer->id, $show->id, $designerTitle, $designerBody);

        $firebase->sendToUser($model, $modelTitle, $modelBody, [
            'screen'  => 'shows',
            'show_id' => (string) $show->id,
            'type'    => 'invitation_expired',
        ]);
        $firebase->sendToUser($designer, $designerTitle, $designerBody, [
            'screen'  => 'shows',
            'show_id' => (string) $show->id,
            'type'    => 'invitation_expired',
        ]);
    }

    private function record(int $recipientId, int $senderId, int $showId, string $title, string $body): void
    {
        DB::table('notifications')->insert([
            'id'              => (string) Str::uuid(),
            'type'            => 'App\\Notifications\\CastingNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id'   => $recipientId,
            'data'            => json_encode([
                'title'   => $title,
                'body'    => $body,
                'screen'  => 'shows',
                'show_id' => $showId,
                'sent_by' => $senderId,
                'type'    => 'invitation_expired',
            ]),
            'read_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ExpireInvitationJob failed for show_model {$this->showModelId}: " . $exception->getMessage());
    }
}
