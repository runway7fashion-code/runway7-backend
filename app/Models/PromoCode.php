<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'description', 'discount_type', 'discount_value',
        'minimum_amount', 'maximum_discount', 'max_uses', 'max_uses_per_user',
        'times_used', 'event_id', 'applicable_to', 'valid_from', 'valid_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'applicable_to' => 'array',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function event() { return $this->belongsTo(Event::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function usages() { return $this->hasMany(PromoCodeUsage::class); }

    public function isValid(): bool
    {
        return $this->is_active
            && now()->between($this->valid_from, $this->valid_until)
            && ($this->max_uses === null || $this->times_used < $this->max_uses);
    }
}
