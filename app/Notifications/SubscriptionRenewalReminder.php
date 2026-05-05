<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalReminder extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $daysAhead,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $when = match ($this->daysAhead) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$this->daysAhead} days",
        };

        $amount = number_format((float) $this->subscription->amount, 2);
        $card = $this->subscription->paymentMethod
            ? " — charged to {$this->subscription->paymentMethod->masked}"
            : '';

        return [
            'type' => 'subscription_renewal',
            'title' => "Subscription renewal {$when}: {$this->subscription->name}",
            'message' => "{$this->subscription->name} renews {$when} for \${$amount}{$card}.",
            'subscription_id' => $this->subscription->id,
            'days_ahead' => $this->daysAhead,
            'renewal_date' => $this->subscription->next_renewal_date?->toDateString(),
            'url' => route('admin.accounting.subscriptions.show', $this->subscription->id),
        ];
    }
}
