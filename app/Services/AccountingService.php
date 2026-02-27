<?php

namespace App\Services;

use App\Models\DesignerInstallment;
use App\Models\DesignerPaymentPlan;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    public function createPaymentPlan(
        int $designerId,
        int $eventId,
        float $totalAmount,
        float $downpayment,
        int $installmentsCount,
        int $createdById,
        ?string $notes = null,
        ?array $customAmounts = null,
        ?array $customDates = null
    ): DesignerPaymentPlan {
        return DB::transaction(function () use ($designerId, $eventId, $totalAmount, $downpayment, $installmentsCount, $createdById, $notes, $customAmounts, $customDates) {
            if (DesignerPaymentPlan::where('designer_id', $designerId)->where('event_id', $eventId)->exists()) {
                throw new \Exception('Ya existe un plan de pagos para este diseñador en este evento.');
            }

            $remaining = $totalAmount - $downpayment;

            $eventDesigner = DB::table('event_designer')
                ->where('designer_id', $designerId)
                ->where('event_id', $eventId)
                ->first();

            $plan = DesignerPaymentPlan::create([
                'designer_id' => $designerId,
                'event_id' => $eventId,
                'package_id' => $eventDesigner->package_id ?? null,
                'created_by' => $createdById,
                'total_amount' => $totalAmount,
                'downpayment' => $downpayment,
                'remaining_amount' => $remaining,
                'installments_count' => $installmentsCount,
                'status' => 'active',
                'notes' => $notes,
            ]);

            if ($installmentsCount > 0 && $remaining > 0) {
                $startDate = now()->startOfMonth()->addMonth();

                $this->generateInstallments($plan, $installmentsCount, $remaining, $startDate, 1, $customAmounts, $customDates);
            }

            return $plan->load('installments');
        });
    }

    public function updatePaymentPlan(
        DesignerPaymentPlan $plan,
        float $totalAmount,
        float $downpayment,
        int $installmentsCount,
        ?string $notes = null,
        ?array $customAmounts = null,
        ?array $customDates = null
    ): DesignerPaymentPlan {
        return DB::transaction(function () use ($plan, $totalAmount, $downpayment, $installmentsCount, $notes, $customAmounts, $customDates) {
            $paidInstallments = $plan->installments()->where('status', 'paid')->get();
            $paidInstallmentsTotal = (float) $paidInstallments->sum('amount');

            // Delete unpaid installments (pending, overdue, cancelled)
            $plan->installments()->where('status', '!=', 'paid')->delete();

            $remaining = $totalAmount - $downpayment;

            $plan->update([
                'total_amount' => $totalAmount,
                'downpayment' => $downpayment,
                'remaining_amount' => $remaining,
                'installments_count' => $installmentsCount,
                'notes' => $notes,
            ]);

            // Generate new unpaid installments
            $newInstallmentsCount = $installmentsCount - $paidInstallments->count();
            $newRemaining = $remaining - $paidInstallmentsTotal;
            $nextNumber = $paidInstallments->count() + 1;

            if ($newInstallmentsCount > 0 && $newRemaining > 0) {
                $startDate = now()->startOfMonth()->addMonth();

                $this->generateInstallments($plan, $newInstallmentsCount, $newRemaining, $startDate, $nextNumber, $customAmounts, $customDates);
            }

            // Re-check if completed
            $this->checkIfCompleted($plan);

            return $plan->fresh(['installments']);
        });
    }

    public function markDownpaymentPaid(DesignerPaymentPlan $plan, ?string $receiptPath = null): void
    {
        $plan->update([
            'downpayment_status' => 'paid',
            'downpayment_paid_at' => now(),
            'downpayment_receipt' => $receiptPath,
        ]);

        $this->checkIfCompleted($plan);
    }

    public function markInstallmentPaid(
        DesignerInstallment $installment,
        int $markedById,
        string $paymentMethod,
        ?string $paymentReference = null,
        ?string $receiptPath = null,
        ?string $notes = null
    ): void {
        $installment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'marked_by' => $markedById,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'receipt_url' => $receiptPath,
            'notes' => $notes,
        ]);

        $this->checkIfCompleted($installment->paymentPlan);
    }

    private function generateInstallments(
        DesignerPaymentPlan $plan,
        int $count,
        float $remaining,
        \Carbon\Carbon $startDate,
        int $startNumber = 1,
        ?array $customAmounts = null,
        ?array $customDates = null
    ): void {
        if ($customAmounts) {
            // Validate sum matches remaining
            $sum = round(array_sum($customAmounts), 2);
            if (abs($sum - $remaining) > 0.01) {
                throw new \Exception("La suma de las cuotas (\${$sum}) no coincide con el monto restante (\${$remaining}).");
            }

            foreach ($customAmounts as $i => $amount) {
                $dueDate = isset($customDates[$i])
                    ? \Carbon\Carbon::parse($customDates[$i])
                    : $startDate->copy()->addMonths($i);

                $plan->installments()->create([
                    'installment_number' => $startNumber + $i,
                    'amount' => round((float) $amount, 2),
                    'due_date' => $dueDate,
                    'status' => 'pending',
                ]);
            }
        } else {
            $installmentAmount = round($remaining / $count, 2);

            for ($i = 0; $i < $count; $i++) {
                $amount = $installmentAmount;

                if ($i === $count - 1) {
                    $previousTotal = $installmentAmount * ($count - 1);
                    $amount = round($remaining - $previousTotal, 2);
                }

                $dueDate = isset($customDates[$i])
                    ? \Carbon\Carbon::parse($customDates[$i])
                    : $startDate->copy()->addMonths($i);

                $plan->installments()->create([
                    'installment_number' => $startNumber + $i,
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'status' => 'pending',
                ]);
            }
        }
    }

    private function checkIfCompleted(DesignerPaymentPlan $plan): void
    {
        $plan->refresh();
        if ($plan->isFullyPaid()) {
            $plan->update(['status' => 'completed']);
        }
    }

    public function allocatePaymentToInstallments(
        int $designerId,
        int $eventId,
        float $amount,
        int $markedById,
        string $paymentMethod,
        ?string $reference = null
    ): void {
        $plan = DesignerPaymentPlan::where('designer_id', $designerId)
            ->where('event_id', $eventId)
            ->first();

        if (!$plan || $amount <= 0) return;

        DB::transaction(function () use ($plan, $amount, $markedById, $paymentMethod, $reference) {
            $remaining = $amount;

            // Get installments with balance remaining, ordered: overdue first, then partial, then pending, by due_date
            $installments = $plan->installments()
                ->whereIn('status', ['overdue', 'partial', 'pending'])
                ->orderByRaw("CASE status WHEN 'overdue' THEN 1 WHEN 'partial' THEN 2 WHEN 'pending' THEN 3 END")
                ->orderBy('due_date')
                ->get();

            foreach ($installments as $installment) {
                if ($remaining <= 0) break;

                $installmentRemaining = $installment->remainingAmount();
                if ($installmentRemaining <= 0) continue;

                $toApply = min($remaining, $installmentRemaining);
                $newPaidAmount = round((float) $installment->paid_amount + $toApply, 2);

                $updateData = ['paid_amount' => $newPaidAmount];

                if ($newPaidAmount >= (float) $installment->amount) {
                    // Fully paid
                    $updateData['status'] = 'paid';
                    $updateData['paid_at'] = now();
                    $updateData['marked_by'] = $markedById;
                    $updateData['payment_method'] = $paymentMethod;
                    $updateData['payment_reference'] = $reference;
                } else {
                    // Partial payment
                    $updateData['status'] = 'partial';
                }

                $installment->update($updateData);
                $remaining = round($remaining - $toApply, 2);
            }

            $this->checkIfCompleted($plan);
        });
    }

    public function allocateDownpayment(int $designerId, int $eventId): void
    {
        $plan = DesignerPaymentPlan::where('designer_id', $designerId)
            ->where('event_id', $eventId)
            ->first();

        if (!$plan || $plan->downpayment_status === 'paid') return;

        $this->markDownpaymentPaid($plan);
    }

    public function updateOverdueInstallments(): int
    {
        return DesignerInstallment::whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', now())
            ->update(['status' => 'overdue']);
    }

    public function getDashboardStats(?int $eventId = null): array
    {
        $query = DesignerPaymentPlan::query();
        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $plans = $query->with(['installments', 'package'])->get();

        $totalExpected = $plans->sum('total_amount');
        $totalCollected = $plans->sum(fn($p) => $p->totalPaid());
        $totalPending = $totalExpected - $totalCollected;
        $totalOverdue = $plans->sum(fn($p) => (float) $p->installments
            ->filter(fn($i) => in_array($i->status, ['overdue']) || (in_array($i->status, ['pending', 'partial']) && $i->due_date < now()))
            ->sum(fn($i) => $i->amount - $i->paid_amount)
        );

        $byPackage = $plans->groupBy('package_id')->map(function ($group) {
            $packageName = $group->first()->package->name ?? 'Sin paquete';
            return [
                'package' => $packageName,
                'count' => $group->count(),
                'total' => (float) $group->sum('total_amount'),
                'collected' => $group->sum(fn($p) => $p->totalPaid()),
                'pending' => $group->sum(fn($p) => $p->totalPending()),
            ];
        })->values();

        $installmentsQuery = DesignerInstallment::query();
        if ($eventId) {
            $installmentsQuery->whereHas('paymentPlan', fn($q) => $q->where('event_id', $eventId));
        }
        $byMonth = $installmentsQuery->get()->groupBy(fn($i) => $i->due_date->format('Y-m'))->map(function ($group, $month) {
            return [
                'month' => $month,
                'total' => (float) $group->sum('amount'),
                'paid' => (float) $group->where('status', 'paid')->sum('amount'),
                'pending' => (float) $group->where('status', '!=', 'paid')->sum('amount'),
                'count' => $group->count(),
                'paid_count' => $group->where('status', 'paid')->count(),
            ];
        })->sortKeys()->values();

        $recentPlans = DesignerPaymentPlan::with(['designer.designerProfile', 'package', 'installments'])
            ->when($eventId, fn($q) => $q->where('event_id', $eventId))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'designer_name' => $p->designer->first_name . ' ' . $p->designer->last_name,
                'brand' => $p->designer->designerProfile?->brand_name,
                'package' => $p->package?->name ?? 'Sin paquete',
                'total' => (float) $p->total_amount,
                'paid' => $p->totalPaid(),
                'pending' => $p->totalPending(),
                'progress' => $p->progressPercentage(),
                'status' => $p->status,
                'designer_id' => $p->designer_id,
                'event_id' => $p->event_id,
            ]);

        return [
            'total_expected' => $totalExpected,
            'total_collected' => $totalCollected,
            'total_pending' => $totalPending,
            'total_overdue' => $totalOverdue,
            'plans_count' => $plans->count(),
            'completed_plans' => $plans->where('status', 'completed')->count(),
            'by_package' => $byPackage,
            'by_month' => $byMonth,
            'recent_plans' => $recentPlans,
            'collection_percentage' => $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 1) : 0,
        ];
    }
}
