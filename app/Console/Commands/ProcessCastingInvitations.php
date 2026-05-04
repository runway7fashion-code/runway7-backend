<?php

namespace App\Console\Commands;

use App\Jobs\ExpireInvitationJob;
use App\Jobs\ExpireInvitationReminderJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessCastingInvitations extends Command
{
    protected $signature = 'casting:process-invitations';

    protected $description = 'Sweeps requested casting invitations and fires expiration reminders (1h/30m/5m before) and the final expiration. Idempotent — uses notified_*_at flags so no duplicate pushes.';

    /**
     * Runs every minute. For each window, picks invitations whose expires_at falls
     * inside the window AND haven't been notified yet, dispatches the job (sync since
     * the app uses QUEUE_CONNECTION=sync) and stamps the corresponding flag column.
     */
    public function handle(): int
    {
        $now = now();
        $sent = ['1h' => 0, '30m' => 0, '5m' => 0, 'expired' => 0];

        // Reminder windows. We use "<=" so a delayed cron tick still catches the row.
        $rows = DB::table('show_model')
            ->where('status', 'requested')
            ->whereNotNull('expires_at')
            ->whereNull('notified_1h_at')
            ->where('expires_at', '<=', $now->copy()->addHour())
            ->where('expires_at', '>', $now)
            ->pluck('id');
        foreach ($rows as $id) {
            ExpireInvitationReminderJob::dispatchSync($id, '1h');
            DB::table('show_model')->where('id', $id)->update(['notified_1h_at' => now()]);
            $sent['1h']++;
        }

        $rows = DB::table('show_model')
            ->where('status', 'requested')
            ->whereNotNull('expires_at')
            ->whereNull('notified_30m_at')
            ->where('expires_at', '<=', $now->copy()->addMinutes(30))
            ->where('expires_at', '>', $now)
            ->pluck('id');
        foreach ($rows as $id) {
            ExpireInvitationReminderJob::dispatchSync($id, '30m');
            DB::table('show_model')->where('id', $id)->update(['notified_30m_at' => now()]);
            $sent['30m']++;
        }

        $rows = DB::table('show_model')
            ->where('status', 'requested')
            ->whereNotNull('expires_at')
            ->whereNull('notified_5m_at')
            ->where('expires_at', '<=', $now->copy()->addMinutes(5))
            ->where('expires_at', '>', $now)
            ->pluck('id');
        foreach ($rows as $id) {
            ExpireInvitationReminderJob::dispatchSync($id, '5m');
            DB::table('show_model')->where('id', $id)->update(['notified_5m_at' => now()]);
            $sent['5m']++;
        }

        // Final expiration sweep.
        $rows = DB::table('show_model')
            ->where('status', 'requested')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->pluck('id');
        foreach ($rows as $id) {
            ExpireInvitationJob::dispatchSync($id);
            $sent['expired']++;
        }

        $this->info("Reminders 1h={$sent['1h']} 30m={$sent['30m']} 5m={$sent['5m']} expired={$sent['expired']}");

        return self::SUCCESS;
    }
}
