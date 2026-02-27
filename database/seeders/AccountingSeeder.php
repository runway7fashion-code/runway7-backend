<?php

namespace Database\Seeders;

use App\Models\DesignerPaymentPlan;
use App\Models\DesignerPackage;
use App\Models\Event;
use App\Models\PaymentRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::where('slug', 'nyfw-september-2026')->first();
        if (!$event) return;

        $designer1 = User::where('email', 'ale@nocturnadesign.com')->first();
        $designer2 = User::where('email', 'val@lunawhite.com')->first();
        $accounting = User::where('email', 'accounting@runway7.com')->first();

        $premiumPkg  = DesignerPackage::where('slug', 'premium')->first();
        $platinumPkg = DesignerPackage::where('slug', 'platinum')->first();

        // Plan 1: Alejandro Vasquez / NYFW — $5,000
        // Downpayment $1,000 (paid), 4 cuotas $1,000 (2 paid, 2 pending)
        if ($designer1 && $accounting) {
            $plan1 = DesignerPaymentPlan::create([
                'designer_id'       => $designer1->id,
                'event_id'          => $event->id,
                'package_id'        => $premiumPkg?->id,
                'created_by'        => $accounting->id,
                'total_amount'      => 5000.00,
                'downpayment'       => 1000.00,
                'remaining_amount'  => 4000.00,
                'installments_count'=> 4,
                'downpayment_status'=> 'paid',
                'downpayment_paid_at'=> now()->subDays(45),
                'status'            => 'active',
                'notes'             => 'Plan creado al confirmar participacion en NYFW.',
            ]);

            // 4 cuotas de $1,000 — 2 paid, 1 partial/overdue ($300/$1000), 1 pending
            for ($i = 1; $i <= 4; $i++) {
                $isPaid = $i <= 2;
                $isPartial = $i === 3;

                // Cuotas 1-3 en el pasado, cuota 4 en el futuro
                $dueDate = $i <= 3
                    ? now()->startOfMonth()->subMonths(4 - $i)
                    : now()->startOfMonth()->addMonth();

                $plan1->installments()->create([
                    'installment_number' => $i,
                    'amount'             => 1000.00,
                    'paid_amount'        => $isPaid ? 1000.00 : ($isPartial ? 300.00 : 0),
                    'due_date'           => $dueDate,
                    'status'             => $isPaid ? 'paid' : ($isPartial ? 'partial' : 'pending'),
                    'paid_at'            => $isPaid ? now()->subDays(30 - ($i * 10)) : null,
                    'marked_by'          => $isPaid ? $accounting->id : null,
                    'payment_method'     => $isPaid ? 'wire_transfer' : null,
                    'payment_reference'  => $isPaid ? 'REF-' . str_pad($i, 4, '0', STR_PAD_LEFT) : null,
                ]);
            }
        }

        // Plan 2: Valentina Morales / NYFW — $7,500
        // Downpayment $2,500 (paid), 3 cuotas ~$1,666.67 (todas pending)
        if ($designer2 && $accounting) {
            $remaining = 5000.00;
            $installmentAmt = round($remaining / 3, 2);

            $plan2 = DesignerPaymentPlan::create([
                'designer_id'       => $designer2->id,
                'event_id'          => $event->id,
                'package_id'        => $platinumPkg?->id,
                'created_by'        => $accounting->id,
                'total_amount'      => 7500.00,
                'downpayment'       => 2500.00,
                'remaining_amount'  => $remaining,
                'installments_count'=> 3,
                'downpayment_status'=> 'paid',
                'downpayment_paid_at'=> now()->subDays(30),
                'status'            => 'active',
                'notes'             => 'Paquete Platinum. Downpayment recibido via Zelle.',
            ]);

            for ($i = 1; $i <= 3; $i++) {
                $amount = $installmentAmt;
                if ($i === 3) {
                    $amount = round($remaining - $installmentAmt * 2, 2);
                }

                // Cuotas 1 y 2 vencidas (en el pasado), cuota 3 futura
                $dueDate = now()->startOfMonth()->subMonths(3 - $i);
                $isOverdue = $i <= 2;

                $plan2->installments()->create([
                    'installment_number' => $i,
                    'amount'             => $amount,
                    'paid_amount'        => 0,
                    'due_date'           => $isOverdue ? $dueDate : now()->startOfMonth()->addMonth(),
                    'status'             => $isOverdue ? 'overdue' : 'pending',
                ]);
            }
        }

        // --- Payment Records (Registro de Pagos) ---
        if ($designer1 && $accounting && $event) {
            PaymentRecord::create([
                'designer_id'    => $designer1->id,
                'event_id'       => $event->id,
                'amount'         => 1000.00,
                'payment_type'   => 'downpayment',
                'payment_method' => 'wire_transfer',
                'reference'      => 'Alejandro V. - Chase Bank',
                'registered_by'  => $accounting->id,
                'payment_date'   => '2026-01-15 10:30:00',
                'notes'          => 'Downpayment inicial recibido por transferencia.',
            ]);

            PaymentRecord::create([
                'designer_id'    => $designer1->id,
                'event_id'       => $event->id,
                'amount'         => 1000.00,
                'payment_type'   => 'installment',
                'payment_method' => 'zelle',
                'reference'      => 'A. Vasquez',
                'registered_by'  => $accounting->id,
                'payment_date'   => '2026-03-01 14:15:00',
            ]);

            // Pago parcial cuota 3 — $300 de $1,000
            PaymentRecord::create([
                'designer_id'    => $designer1->id,
                'event_id'       => $event->id,
                'amount'         => 300.00,
                'payment_type'   => 'installment',
                'payment_method' => 'venmo',
                'reference'      => 'Alejandro V. - abono cuota 3',
                'registered_by'  => $accounting->id,
                'payment_date'   => '2026-02-20 11:00:00',
                'notes'          => 'Pago parcial de cuota 3.',
            ]);
        }

        if ($designer2 && $accounting && $event) {
            PaymentRecord::create([
                'designer_id'    => $designer2->id,
                'event_id'       => $event->id,
                'amount'         => 2500.00,
                'payment_type'   => 'downpayment',
                'payment_method' => 'venmo',
                'reference'      => 'Val Morales',
                'registered_by'  => $accounting->id,
                'payment_date'   => '2026-02-01 09:00:00',
            ]);

            PaymentRecord::create([
                'designer_id'    => $designer2->id,
                'event_id'       => $event->id,
                'amount'         => 1000.00,
                'payment_type'   => 'installment',
                'payment_method' => 'stripe',
                'reference'      => 'Valentina M.',
                'registered_by'  => $accounting->id,
                'payment_date'   => '2026-03-05 16:45:00',
                'notes'          => 'Pago via Stripe checkout.',
            ]);
        }
    }
}
