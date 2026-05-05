<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionRenewalReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendSubscriptionRenewalReminders extends Command
{
    protected $signature = 'subscriptions:send-renewal-reminders';

    protected $description = 'Sends in-app reminders to admin and accounting users for subscriptions renewing in 7, 3 and 0 days';

    /**
     * Idempotency relies on running once per day. Each window (e.g. renewal == today + 7)
     * matches once over the lifecycle of the renewal date.
     */
    public function handle(): int
    {
        $recipients = User::whereIn('role', ['admin', 'accounting'])
            ->where('status', 'active')
            ->get();

        if ($recipients->isEmpty()) {
            $this->info('No admin/accounting recipients to notify.');
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ([7, 3, 0] as $offset) {
            $sent += $this->dispatchForOffset($offset, $recipients);
        }

        $this->info("Renewal reminders dispatched: {$sent}");
        return self::SUCCESS;
    }

    private function dispatchForOffset(int $offset, $recipients): int
    {
        $targetDate = now()->addDays($offset)->toDateString();

        $subs = Subscription::with('paymentMethod')
            ->where('status', 'active')
            ->whereDate('next_renewal_date', $targetDate)
            ->get();

        $count = 0;
        foreach ($subs as $sub) {
            if ($this->alreadyNotified($sub->id, $offset, $targetDate)) {
                continue;
            }
            foreach ($recipients as $user) {
                $user->notify(new SubscriptionRenewalReminder($sub, $offset));
            }
            $count++;
        }

        return $count;
    }

    private function alreadyNotified(int $subscriptionId, int $offset, string $targetDate): bool
    {
        return DB::table('notifications')
            ->where('type', SubscriptionRenewalReminder::class)
            ->whereDate('created_at', now()->toDateString())
            ->where('data->subscription_id', $subscriptionId)
            ->where('data->days_ahead', $offset)
            ->where('data->renewal_date', $targetDate)
            ->exists();
    }
}
