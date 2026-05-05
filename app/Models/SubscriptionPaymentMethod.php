<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'nickname', 'card_type', 'last_four', 'holder_name', 'notes',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'payment_method_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'payment_method_id');
    }

    public function getMaskedAttribute(): string
    {
        $type = ucfirst($this->card_type);
        return "{$type} ****{$this->last_four}";
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->nickname} ({$this->masked})";
    }
}
