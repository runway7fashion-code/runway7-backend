<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'designer_id', 'event_id', 'amount', 'payment_type',
        'payment_method', 'reference', 'receipt_url', 'notes',
        'registered_by', 'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'datetime',
        ];
    }

    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function registeredBy() { return $this->belongsTo(User::class, 'registered_by'); }

    public function getDesignerBrandAttribute(): string
    {
        return $this->designer->designerProfile?->brand_name ?? $this->designer->first_name;
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'wire_transfer' => 'Transferencia',
            'venmo' => 'Venmo',
            'zelle' => 'Zelle',
            'stripe' => 'Stripe',
            'cash' => 'Efectivo',
            'check' => 'Cheque',
            'other' => 'Otro',
            default => $this->payment_method,
        };
    }

    public function getPaymentTypeLabelAttribute(): string
    {
        return match ($this->payment_type) {
            'downpayment' => 'Downpayment',
            'installment' => 'Cuota',
            default => $this->payment_type,
        };
    }
}
