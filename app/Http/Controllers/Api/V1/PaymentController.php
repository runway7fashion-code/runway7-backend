<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DesignerPaymentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Planes de pago del diseñador autenticado.
     */
    public function myPayments(Request $request): JsonResponse
    {
        $user = $request->user();

        $plans = DesignerPaymentPlan::where('designer_id', $user->id)
            ->with(['event:id,name,city', 'package:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $plans->map(function ($plan) {
            return [
                'id' => $plan->id,
                'event' => $plan->event ? [
                    'id' => $plan->event->id,
                    'name' => $plan->event->name,
                ] : null,
                'package' => $plan->package?->name,
                'total_amount' => $plan->total_amount,
                'downpayment' => $plan->downpayment,
                'downpayment_status' => $plan->downpayment_status,
                'remaining_amount' => $plan->remaining_amount,
                'installments_count' => $plan->installments_count,
                'total_paid' => $plan->totalPaid(),
                'total_pending' => $plan->totalPending(),
                'progress' => $plan->progressPercentage(),
                'status' => $plan->status,
            ];
        });

        return response()->json(['payment_plans' => $data]);
    }

    /**
     * Detalle de un plan de pago con sus cuotas.
     */
    public function show(Request $request, DesignerPaymentPlan $plan): JsonResponse
    {
        $user = $request->user();

        if ($plan->designer_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $plan->load(['event:id,name,city', 'package:id,name', 'installments']);

        return response()->json([
            'payment_plan' => [
                'id' => $plan->id,
                'event' => $plan->event ? [
                    'id' => $plan->event->id,
                    'name' => $plan->event->name,
                ] : null,
                'package' => $plan->package?->name,
                'total_amount' => $plan->total_amount,
                'downpayment' => $plan->downpayment,
                'downpayment_status' => $plan->downpayment_status,
                'downpayment_paid_at' => $plan->downpayment_paid_at?->toIso8601String(),
                'remaining_amount' => $plan->remaining_amount,
                'installments_count' => $plan->installments_count,
                'total_paid' => $plan->totalPaid(),
                'total_pending' => $plan->totalPending(),
                'progress' => $plan->progressPercentage(),
                'status' => $plan->status,
                'installments' => $plan->installments->map(function ($inst) {
                    return [
                        'id' => $inst->id,
                        'number' => $inst->installment_number,
                        'amount' => $inst->amount,
                        'paid_amount' => $inst->paid_amount,
                        'remaining' => $inst->remainingAmount(),
                        'due_date' => $inst->due_date->format('Y-m-d'),
                        'status' => $inst->status,
                        'is_overdue' => $inst->isOverdue(),
                        'payment_method' => $inst->payment_method,
                        'paid_at' => $inst->paid_at?->toIso8601String(),
                    ];
                }),
            ],
        ]);
    }
}
