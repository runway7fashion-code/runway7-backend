<?php

namespace App\Console\Commands;

use App\Mail\MaterialsDeadlineReminderMail;
use App\Models\DesignerMaterial;
use App\Models\Event;
use App\Models\User;
use App\Notifications\MaterialsDeadlineReminder;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMaterialsDeadlineReminders extends Command
{
    protected $signature = 'materials:send-deadline-reminders
                            {--dry-run : Report what would be sent without actually sending}
                            {--designer= : Limit to a specific designer id}
                            {--event= : Limit to a specific event id}';

    protected $description = 'Send reminders to designers about their materials deadline (email + push)';

    /**
     * Offsets (in days from today) that trigger a reminder.
     * Positive = days until deadline. Zero = today. Negative = overdue.
     */
    private const STAGES = [
        30  => 'early',
        7   => 'upcoming',
        3   => 'soon',
        1   => 'tomorrow',
        0   => 'today',
        -1  => 'overdue',
    ];

    public function handle(FirebaseNotificationService $firebase): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $designerFilter = $this->option('designer');
        $eventFilter    = $this->option('event');

        $today = Carbon::today();

        // event_designer pivot rows with an effective deadline (per-designer override OR event default)
        $query = DB::table('event_designer as ed')
            ->join('users as u', 'u.id', '=', 'ed.designer_id')
            ->join('events as e', 'e.id', '=', 'ed.event_id')
            ->whereRaw('COALESCE(ed.materials_deadline, e.materials_deadline_default) IS NOT NULL')
            ->where('u.role', 'designer')
            ->whereIn('u.status', ['active', 'registered', 'pending'])
            ->selectRaw('
                ed.designer_id,
                ed.event_id,
                COALESCE(ed.materials_deadline, e.materials_deadline_default) as materials_deadline,
                u.first_name,
                u.last_name,
                u.email,
                e.name as event_name
            ');

        if ($designerFilter) $query->where('ed.designer_id', $designerFilter);
        if ($eventFilter)    $query->where('ed.event_id', $eventFilter);

        $rows = $query->get();

        $processed = 0;
        $sent = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $processed++;
            $deadline = Carbon::parse($row->materials_deadline);
            $daysRemaining = (int) $today->diffInDays($deadline, false); // negative = past

            // Determine stage
            $stage = null;
            foreach (self::STAGES as $offset => $stageName) {
                if ($offset === -1) {
                    // 'overdue' stage fires only the day AFTER the deadline
                    if ($daysRemaining === -1) {
                        $stage = $stageName;
                        break;
                    }
                } elseif ($daysRemaining === $offset) {
                    $stage = $stageName;
                    break;
                }
            }

            if (!$stage) {
                $skipped++;
                continue;
            }

            // Only notify if designer still has pending materials for this event
            $pendingCount = DesignerMaterial::countPendingForDesigner($row->designer_id, $row->event_id);
            if ($pendingCount === 0) {
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                '[%s] %s %s → %s (%s pending, deadline %s)',
                strtoupper($stage),
                $row->first_name,
                $row->last_name,
                $row->event_name,
                $pendingCount,
                $deadline->toDateString(),
            ));

            if ($dryRun) {
                $sent++;
                continue;
            }

            $this->dispatch($row, $stage, $daysRemaining, $pendingCount, $firebase);
            $sent++;
        }

        $this->info(sprintf(
            '%sProcessed %d designer-event rows — sent %d, skipped %d',
            $dryRun ? '[DRY-RUN] ' : '',
            $processed,
            $sent,
            $skipped,
        ));

        return self::SUCCESS;
    }

    private function dispatch(object $row, string $stage, int $daysRemaining, int $pendingCount, FirebaseNotificationService $firebase): void
    {
        $designer = User::find($row->designer_id);
        if (!$designer) return;

        $deadlineDate = Carbon::parse($row->materials_deadline)->format('F j, Y');

        // 1. In-app notification (database)
        try {
            $designer->notify(new MaterialsDeadlineReminder(
                eventName:     $row->event_name,
                deadlineDate:  $deadlineDate,
                daysRemaining: $daysRemaining,
                pendingCount:  $pendingCount,
                stage:         $stage,
                eventId:       $row->event_id,
            ));
        } catch (\Throwable $e) {
            Log::warning("Materials deadline: in-app notification failed for user {$designer->id}: " . $e->getMessage());
        }

        // 2. Push notification (Firebase)
        try {
            $pushTitle = match ($stage) {
                'early'    => 'Materials deadline reminder',
                'upcoming' => '1 week left',
                'soon'     => '3 days left',
                'tomorrow' => 'Deadline tomorrow',
                'today'    => 'Deadline today',
                'overdue'  => 'Deadline passed',
                default    => 'Materials reminder',
            };

            $pushBody = $stage === 'overdue'
                ? "Uploads for {$row->event_name} are blocked. Contact your advisor."
                : "{$pendingCount} pending for {$row->event_name}. Due {$deadlineDate}.";

            $firebase->sendToUser($designer, $pushTitle, $pushBody, [
                'type'     => 'materials_deadline_reminder',
                'stage'    => $stage,
                'event_id' => (string) $row->event_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Materials deadline: push failed for user {$designer->id}: " . $e->getMessage());
        }

        // 3. Email
        try {
            if (!$designer->email) return;
            Mail::to($designer->email, "{$designer->first_name} {$designer->last_name}")
                ->send(new MaterialsDeadlineReminderMail(
                    designer:      $designer,
                    eventName:     $row->event_name,
                    deadlineDate:  $deadlineDate,
                    daysRemaining: $daysRemaining,
                    pendingCount:  $pendingCount,
                    stage:         $stage,
                ));
        } catch (\Throwable $e) {
            Log::warning("Materials deadline: email failed for user {$designer->id}: " . $e->getMessage());
        }
    }
}
