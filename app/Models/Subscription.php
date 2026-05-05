<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'vendor', 'description', 'account_email', 'department', 'category',
        'billing_cycle', 'amount', 'payment_method_id', 'purchase_date', 'next_renewal_date',
        'auto_renew', 'status', 'plan_tier', 'seats', 'website_url', 'notes',
        'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'purchase_date' => 'date',
            'next_renewal_date' => 'date',
            'auto_renew' => 'boolean',
            'cancelled_at' => 'datetime',
            'seats' => 'integer',
        ];
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPaymentMethod::class, 'payment_method_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->orderByDesc('paid_at');
    }

    public function getMonthlyEquivalentAttribute(): float
    {
        return match ($this->billing_cycle) {
            'monthly' => (float) $this->amount,
            'quarterly' => (float) $this->amount / 3,
            'annual' => (float) $this->amount / 12,
            default => 0.0,
        };
    }

    public function getAnnualEquivalentAttribute(): float
    {
        return match ($this->billing_cycle) {
            'monthly' => (float) $this->amount * 12,
            'quarterly' => (float) $this->amount * 4,
            'annual' => (float) $this->amount,
            default => 0.0,
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRenewingWithin($query, int $days)
    {
        return $query->whereDate('next_renewal_date', '<=', now()->addDays($days)->toDateString())
            ->whereDate('next_renewal_date', '>=', now()->toDateString())
            ->where('status', 'active');
    }
}
