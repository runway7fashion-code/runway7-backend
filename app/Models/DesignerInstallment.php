<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignerInstallment extends Model
{
    protected $fillable = [
        'payment_plan_id', 'installment_number', 'amount', 'paid_amount',
        'due_date', 'status', 'receipt_url', 'payment_method',
        'payment_reference', 'paid_at', 'marked_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function paymentPlan() { return $this->belongsTo(DesignerPaymentPlan::class, 'payment_plan_id'); }
    public function markedByUser() { return $this->belongsTo(User::class, 'marked_by'); }

    public function remainingAmount(): float
    {
        return (float) $this->amount - (float) $this->paid_amount;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['pending', 'partial']) && $this->due_date->isPast();
    }
}
