<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanExpiredMediaRegistrations extends Command
{
    protected $signature = 'media:clean-expired-registrations {--hours=48 : Hours after which a pending registration is considered abandoned}';
    protected $description = 'Mark unpaid media registrations older than N hours as expired';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        $expired = DB::table('event_media')
            ->where('payment_status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->update([
                'payment_status' => 'expired',
                'status'         => 'rejected',
                'updated_at'     => now(),
            ]);

        $this->info("Expired {$expired} unpaid media registration(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}
