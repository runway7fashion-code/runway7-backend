<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignerPaymentPlan extends Model
{
    protected $fillable = [
        'designer_id', 'event_id', 'package_id', 'created_by',
        'total_amount', 'downpayment', 'remaining_amount', 'installments_count',
        'downpayment_status', 'downpayment_receipt', 'downpayment_paid_at',
        'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'downpayment' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'downpayment_paid_at' => 'datetime',
        ];
    }

    public function designer() { return $this->belongsTo(User::class, 'designer_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function package() { return $this->belongsTo(DesignerPackage::class, 'package_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function installments() { return $this->hasMany(DesignerInstallment::class, 'payment_plan_id')->orderBy('installment_number'); }

    public function totalPaid(): float
    {
        $downpaymentPaid = $this->downpayment_status === 'paid' ? (float) $this->downpayment : 0;
        $installmentsPaid = (float) $this->installments()->sum('paid_amount');
        return $downpaymentPaid + $installmentsPaid;
    }

    public function totalPending(): float
    {
        return (float) $this->total_amount - $this->totalPaid();
    }

    public function progressPercentage(): int
    {
        if ((float) $this->total_amount == 0) return 100;
        return (int) (($this->totalPaid() / (float) $this->total_amount) * 100);
    }

    public function isFullyPaid(): bool
    {
        return $this->totalPending() <= 0;
    }

    public function overdueInstallments()
    {
        return $this->installments()->whereIn('status', ['pending', 'partial'])->where('due_date', '<', now());
    }
}
