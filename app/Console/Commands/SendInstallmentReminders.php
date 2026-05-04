<?php

namespace App\Console\Commands;

use App\Jobs\SendPaymentNotificationJob;
use App\Models\DesignerInstallment;
use Illuminate\Console\Command;

class SendInstallmentReminders extends Command
{
    protected $signature = 'accounting:send-installment-reminders';

    protected $description = 'Sends push reminders to designers for upcoming and overdue installments (3d/1d/0d before, +1d/+3d/+7d after)';

    /**
     * Idempotency relies on this command running exactly once per day. Each window
     * (e.g. due_date == today + 3) only matches once over the lifecycle of an installment.
     */
    public function handle(): int
    {
        $sent = 0;

        // Pre-due reminders (status pending or partial)
        $sent += $this->sendUpcoming(3, 'installment_due_3d',  'Your installment is due in 3 days', 'Installment of $%s for %s is due on %s.');
        $sent += $this->sendUpcoming(1, 'installment_due_1d',  'Your installment is due tomorrow', 'Installment of $%s for %s is due tomorrow.');
        $sent += $this->sendUpcoming(0, 'installment_due_today', 'Your installment is due today', 'Installment of $%s for %s is due today.');

        // Post-due reminders (only while still in overdue state)
        $sent += $this->sendOverdue(1, 'installment_overdue_d1', 'Your installment is overdue', 'Installment of $%s for %s is overdue. Please regularize the payment.');
        $sent += $this->sendOverdue(3, 'installment_overdue_d3', 'Your installment is 3 days overdue', 'Installment of $%s for %s has been overdue for 3 days.');
        $sent += $this->sendOverdue(7, 'installment_overdue_d7', 'Your installment is 1 week overdue', 'Installment of $%s for %s has been overdue for 1 week.');

        $this->info("Reminders dispatched: {$sent}");

        return self::SUCCESS;
    }

    private function sendUpcoming(int $offsetDays, string $type, string $title, string $bodyTpl): int
    {
        $targetDate = now()->addDays($offsetDays)->toDateString();
        return $this->dispatchForDueDate($targetDate, ['pending', 'partial'], $type, $title, $bodyTpl);
    }

    private function sendOverdue(int $offsetDays, string $type, string $title, string $bodyTpl): int
    {
        $targetDate = now()->subDays($offsetDays)->toDateString();
        return $this->dispatchForDueDate($targetDate, ['overdue'], $type, $title, $bodyTpl);
    }

    /**
     * @param string[] $statuses
     */
    private function dispatchForDueDate(string $targetDate, array $statuses, string $type, string $title, string $bodyTpl): int
    {
        $installments = DesignerInstallment::with('paymentPlan.event:id,name')
            ->whereIn('status', $statuses)
            ->whereDate('due_date', $targetDate)
            ->get();

        $count = 0;
        foreach ($installments as $inst) {
            $plan = $inst->paymentPlan;
            if (!$plan) continue;

            $eventName = $plan->event?->name ?? 'the event';
            $body = sprintf($bodyTpl, number_format((float) $inst->amount, 2), $eventName, $inst->due_date->format('M d'));

            SendPaymentNotificationJob::dispatch(
                recipientId: $plan->designer_id,
                title: $title,
                body: $body,
                eventId: $plan->event_id,
                type: $type,
                installmentId: $inst->id,
            );
            $count++;
        }

        return $count;
    }
}
