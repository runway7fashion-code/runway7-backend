<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignerCategory;
use App\Models\DesignerInstallment;
use App\Models\DesignerPackage;
use App\Models\DesignerPaymentPlan;
use App\Models\Event;
use App\Models\PaymentRecord;
use App\Models\DesignerContactEmail;
use App\Models\SupportCase;
use App\Models\SupportCaseAttachment;
use App\Models\SalesDocument;
use App\Models\SalesRegistration;
use App\Models\SupportCaseMessage;
use App\Models\User;
use App\Services\AccountingService;
use Carbon\Carbon;
use App\Notifications\DesignerStatusChanged;
use App\Notifications\PaymentPlanAssigned;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function __construct(protected AccountingService $accountingService) {}

    public function dashboard(Request $request): Response
    {
        // Update overdue statuses before showing dashboard
        $this->accountingService->updateOverdueInstallments();

        $eventId = $request->filled('event') ? (int) $request->event : null;

        $stats = $this->accountingService->getDashboardStats($eventId);

        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        return Inertia::render('Admin/Accounting/Dashboard', [
            'stats' => $stats,
            'events' => $events,
            'selectedEvent' => $eventId,
        ]);
    }

    public function payments(Request $request): Response
    {
        $events = Event::whereIn('status', ['published', 'active', 'draft'])
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Accounting/Payments', [
            'events' => $events,
        ]);
    }

    public function designersByEvent(Request $request, Event $event): JsonResponse
    {
        $search = $request->get('search', '');

        // Diseñadores asignados al evento (event_designer)
        $designers = $event->designers()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$search}%"));
                });
            })
            ->with(['designerProfile'])
            ->orderByDesc('users.created_at')
            ->limit(20)
            ->get()
            ->map(function ($designer) use ($event) {
                $plan = DesignerPaymentPlan::where('designer_id', $designer->id)
                    ->where('event_id', $event->id)
                    ->first();

                return [
                    'id' => $designer->id,
                    'first_name' => $designer->first_name,
                    'last_name' => $designer->last_name,
                    'email' => $designer->email,
                    'profile_picture' => $designer->profile_picture,
                    'brand_name' => $designer->designerProfile?->brand_name,
                    'package_price' => $designer->pivot->package_price,
                    'package_id' => $designer->pivot->package_id,
                    'has_plan' => $plan !== null,
                    'plan_status' => $plan?->status,
                    'plan_progress' => $plan?->progressPercentage(),
                    'created_at' => $designer->created_at?->toIso8601String(),
                ];
            });

        // Diseñadores de sales_registrations que aún no están en event_designer
        $assignedIds = $designers->pluck('id');
        $salesDesigners = SalesRegistration::where('event_id', $event->id)
            ->whereNotIn('designer_id', $assignedIds)
            ->with(['designer.designerProfile', 'package'])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('designer', function ($sub) use ($search) {
                    $sub->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($reg) use ($event) {
                $d = $reg->designer;
                $plan = DesignerPaymentPlan::where('designer_id', $d->id)
                    ->where('event_id', $event->id)
                    ->first();
                return [
                    'id' => $d->id,
                    'first_name' => $d->first_name,
                    'last_name' => $d->last_name,
                    'email' => $d->email,
                    'profile_picture' => $d->profile_picture,
                    'brand_name' => $d->designerProfile?->brand_name,
                    'package_price' => $reg->agreed_price,
                    'package_id' => $reg->package_id,
                    'has_plan' => $plan !== null,
                    'plan_status' => $plan?->status,
                    'plan_progress' => $plan?->progressPercentage(),
                    'created_at' => $d->created_at?->toIso8601String(),
                ];
            });

        $all = $designers->concat($salesDesigners)->sortByDesc('created_at')->values();
        return response()->json($all);
    }

    public function designersAllEvents(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        $query = DB::table('event_designer')
            ->join('users', 'users.id', '=', 'event_designer.designer_id')
            ->join('events', 'events.id', '=', 'event_designer.event_id')
            ->leftJoin('designer_profiles', 'designer_profiles.user_id', '=', 'users.id')
            ->whereNull('users.deleted_at')
            ->select([
                'users.id as designer_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.profile_picture',
                'designer_profiles.brand_name',
                'event_designer.event_id',
                'event_designer.package_price',
                'event_designer.package_id',
                'events.name as event_name',
                'users.created_at as user_created_at',
            ])
            ->orderByDesc('users.created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.first_name', 'ilike', "%{$search}%")
                    ->orWhere('users.last_name', 'ilike', "%{$search}%")
                    ->orWhere('users.email', 'ilike', "%{$search}%")
                    ->orWhere('designer_profiles.brand_name', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->limit(40)->get();

        $designerIds = $rows->pluck('designer_id')->unique();
        $eventIds = $rows->pluck('event_id')->unique();

        $plans = DesignerPaymentPlan::whereIn('designer_id', $designerIds)
            ->whereIn('event_id', $eventIds)
            ->get()
            ->keyBy(fn($p) => $p->designer_id . '-' . $p->event_id);

        $results = $rows->map(function ($row) use ($plans) {
            $key = $row->designer_id . '-' . $row->event_id;
            $plan = $plans->get($key);

            return [
                'id' => $row->designer_id,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'email' => $row->email,
                'profile_picture' => $row->profile_picture,
                'brand_name' => $row->brand_name,
                'package_price' => $row->package_price,
                'package_id' => $row->package_id,
                'event_id' => $row->event_id,
                'event_name' => $row->event_name,
                'has_plan' => $plan !== null,
                'plan_status' => $plan?->status,
                'plan_progress' => $plan?->progressPercentage(),
                'created_at' => $row->user_created_at,
            ];
        });

        // Agregar diseñadores de sales_registrations que no están en event_designer
        $assignedPairs = $rows->map(fn($r) => $r->designer_id . '-' . $r->event_id)->toArray();
        $salesQuery = SalesRegistration::with(['designer.designerProfile', 'event', 'package'])
            ->whereHas('designer');

        if ($search) {
            $salesQuery->whereHas('designer', function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$search}%"));
            });
        }

        $salesRegs = $salesQuery->limit(40)->get()
            ->filter(fn($reg) => !in_array($reg->designer_id . '-' . $reg->event_id, $assignedPairs))
            ->map(function ($reg) {
                $d = $reg->designer;
                $plan = DesignerPaymentPlan::where('designer_id', $d->id)
                    ->where('event_id', $reg->event_id)
                    ->first();
                return [
                    'id' => $d->id,
                    'first_name' => $d->first_name,
                    'last_name' => $d->last_name,
                    'email' => $d->email,
                    'profile_picture' => $d->profile_picture,
                    'brand_name' => $d->designerProfile?->brand_name,
                    'package_price' => $reg->agreed_price,
                    'package_id' => $reg->package_id,
                    'event_id' => $reg->event_id,
                    'event_name' => $reg->event?->name,
                    'has_plan' => $plan !== null,
                    'plan_status' => $plan?->status,
                    'plan_progress' => $plan?->progressPercentage(),
                    'created_at' => $d->created_at?->toIso8601String(),
                ];
            });

        $all = $results->concat($salesRegs)->sortByDesc('created_at')->values();
        return response()->json($all);
    }

    public function showDesignerPayment(User $designer, Event $event): Response
    {
        abort_unless($designer->role === 'designer', 404);

        $designer->load(['designerProfile.category', 'designerProfile.salesRep']);

        $eventDesigner = $designer->eventsAsDesigner()->where('events.id', $event->id)->first();

        // Fallback: buscar en sales_registrations si no está en event_designer
        $salesReg = null;
        if (!$eventDesigner) {
            $salesReg = SalesRegistration::where('designer_id', $designer->id)
                ->where('event_id', $event->id)
                ->first();
            abort_unless($salesReg, 404);
        }

        $plan = DesignerPaymentPlan::where('designer_id', $designer->id)
            ->where('event_id', $event->id)
            ->with(['installments.markedByUser', 'package'])
            ->first();

        $packages = DesignerPackage::ordered()->get(['id', 'name', 'price']);

        // Si no tenemos salesReg aún, buscarlo para obtener el downpayment sugerido
        if (!$salesReg) {
            $salesReg = SalesRegistration::where('designer_id', $designer->id)
                ->where('event_id', $event->id)
                ->first();
        }

        // Datos del evento/paquete desde event_designer o sales_registrations
        $packageId = $eventDesigner ? $eventDesigner->pivot->package_id : $salesReg->package_id;
        $packagePrice = $eventDesigner ? $eventDesigner->pivot->package_price : $salesReg->agreed_price;
        $looks = $eventDesigner ? $eventDesigner->pivot->looks : null;
        $suggestedDownpayment = $salesReg?->downpayment;

        return Inertia::render('Admin/Accounting/DesignerPayment', [
            'designer' => [
                'id' => $designer->id,
                'first_name' => $designer->first_name,
                'last_name' => $designer->last_name,
                'email' => $designer->email,
                'phone' => $designer->phone,
                'profile_picture' => $designer->profile_picture,
                'brand_name' => $designer->designerProfile?->brand_name,
                'category' => $designer->designerProfile?->category?->name,
                'category_id' => $designer->designerProfile?->category_id,
                'sales_rep' => $designer->designerProfile?->salesRep ? [
                    'id' => $designer->designerProfile->salesRep->id,
                    'name' => $designer->designerProfile->salesRep->first_name . ' ' . $designer->designerProfile->salesRep->last_name,
                ] : null,
                'sales_rep_id' => $designer->designerProfile?->sales_rep_id,
                'status' => $designer->status,
            ],
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'package_id' => $packageId,
                'package_name' => $packageId ? DesignerPackage::find($packageId)?->name : null,
                'package_price' => $packagePrice,
                'looks' => $looks,
                'suggested_downpayment' => $suggestedDownpayment ? (float) $suggestedDownpayment : null,
                'suggested_installments_count' => $salesReg?->installments_count,
            ],
            'plan' => $plan ? [
                'id' => $plan->id,
                'total_amount' => (float) $plan->total_amount,
                'downpayment' => (float) $plan->downpayment,
                'remaining_amount' => (float) $plan->remaining_amount,
                'installments_count' => $plan->installments_count,
                'downpayment_status' => $plan->downpayment_status,
                'downpayment_receipt' => $plan->downpayment_receipt,
                'downpayment_paid_at' => $plan->downpayment_paid_at?->format('Y-m-d H:i'),
                'status' => $plan->status,
                'notes' => $plan->notes,
                'total_paid' => $plan->totalPaid(),
                'total_pending' => $plan->totalPending(),
                'progress' => $plan->progressPercentage(),
                'package_name' => $plan->package?->name,
                'installments' => $plan->installments->map(fn($i) => [
                    'id' => $i->id,
                    'number' => $i->installment_number,
                    'amount' => (float) $i->amount,
                    'paid_amount' => (float) $i->paid_amount,
                    'due_date' => $i->due_date->format('Y-m-d'),
                    'status' => $i->isOverdue() ? 'overdue' : $i->status,
                    'receipt_url' => $i->receipt_url,
                    'payment_method' => $i->payment_method,
                    'payment_reference' => $i->payment_reference,
                    'paid_at' => $i->paid_at?->format('Y-m-d H:i'),
                    'marked_by' => $i->markedByUser ? $i->markedByUser->first_name . ' ' . $i->markedByUser->last_name : null,
                    'notes' => $i->notes,
                ])->values(),
            ] : null,
            'packages' => $packages,
            'categories' => DesignerCategory::active()->ordered()->get(['id', 'name']),
            'salesReps' => User::where('role', 'sales')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'documents' => $this->buildDocumentsList($designer, $event, $salesReg, $plan),
        ]);
    }

    private function buildDocumentsList(User $designer, Event $event, ?SalesRegistration $salesReg, ?DesignerPaymentPlan $plan): array
    {
        $docs = [];

        // Documentos subidos por ventas
        if ($salesReg) {
            $salesDocs = SalesDocument::where('sales_registration_id', $salesReg->id)
                ->with('uploader:id,first_name,last_name')
                ->orderBy('created_at')
                ->get();

            foreach ($salesDocs as $doc) {
                $docs[] = [
                    'source'        => 'sales',
                    'label'         => $this->docTypeLabel($doc->type),
                    'original_name' => $doc->original_name,
                    'url'           => '/storage/' . $doc->file_path,
                    'notes'         => $doc->notes,
                    'uploaded_by'   => $doc->uploader ? $doc->uploader->first_name . ' ' . $doc->uploader->last_name : null,
                    'uploaded_at'   => $doc->created_at?->format('Y-m-d'),
                ];
            }
        }

        // Recibo de downpayment (subido por accounting)
        if ($plan?->downpayment_receipt) {
            $docs[] = [
                'source'        => 'accounting',
                'label'         => 'Recibo de Downpayment',
                'original_name' => basename($plan->downpayment_receipt),
                'url'           => '/storage/' . $plan->downpayment_receipt,
                'notes'         => null,
                'uploaded_by'   => null,
                'uploaded_at'   => $plan->downpayment_paid_at?->format('Y-m-d'),
            ];
        }

        // Recibos de cuotas (subidos por accounting)
        foreach ($plan?->installments ?? [] as $inst) {
            if ($inst->receipt_url) {
                $docs[] = [
                    'source'        => 'accounting',
                    'label'         => "Recibo Cuota #{$inst->installment_number}",
                    'original_name' => basename($inst->receipt_url),
                    'url'           => '/storage/' . $inst->receipt_url,
                    'notes'         => $inst->payment_reference ?? null,
                    'uploaded_by'   => $inst->markedByUser ? $inst->markedByUser->first_name . ' ' . $inst->markedByUser->last_name : null,
                    'uploaded_at'   => $inst->paid_at?->format('Y-m-d'),
                ];
            }
        }

        return $docs;
    }

    private function docTypeLabel(string $type): string
    {
        return match ($type) {
            'contract'  => 'Contrato',
            'invoice'   => 'Factura',
            'id'        => 'Identificación',
            'receipt'   => 'Recibo',
            default     => 'Documento',
        };
    }

    public function createPaymentPlan(Request $request)
    {
        $request->validate([
            'designer_id' => 'required|exists:users,id',
            'event_id' => 'required|exists:events,id',
            'total_amount' => 'required|numeric|min:0',
            'downpayment' => 'required|numeric|min:0',
            'installments_count' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'custom_amounts' => 'nullable|array',
            'custom_amounts.*' => 'numeric|min:0.01',
            'custom_dates' => 'nullable|array',
            'custom_dates.*' => 'date',
        ]);

        try {
            $this->accountingService->createPaymentPlan(
                $request->designer_id,
                $request->event_id,
                $request->total_amount,
                $request->downpayment,
                $request->installments_count,
                $request->user()->id,
                $request->notes,
                $request->custom_amounts,
                $request->custom_dates,
            );

            $designer = User::with('designerProfile')->find($request->designer_id);
            $notifyUsers = User::whereIn('role', ['admin', 'operation'])->get();
            foreach ($notifyUsers as $notifyUser) {
                $notifyUser->notify(new PaymentPlanAssigned($designer, $request->user()));
            }

            return back()->with('success', 'Plan de pagos creado exitosamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['plan' => $e->getMessage()]);
        }
    }

    public function updatePaymentPlan(Request $request, DesignerPaymentPlan $plan)
    {
        $request->validate([
            'total_amount' => 'required|numeric|min:0',
            'downpayment' => 'required|numeric|min:0',
            'installments_count' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'custom_amounts' => 'nullable|array',
            'custom_amounts.*' => 'numeric|min:0.01',
            'custom_dates' => 'nullable|array',
            'custom_dates.*' => 'date',
        ]);

        try {
            $this->accountingService->updatePaymentPlan(
                $plan,
                $request->total_amount,
                $request->downpayment,
                $request->installments_count,
                $request->notes,
                $request->custom_amounts,
                $request->custom_dates,
            );

            return back()->with('success', 'Plan de pagos actualizado.');
        } catch (\Exception $e) {
            return back()->withErrors(['plan' => $e->getMessage()]);
        }
    }

    public function markDownpaymentPaid(Request $request, DesignerPaymentPlan $plan)
    {
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $request->validate(['receipt' => 'file|mimes:jpg,jpeg,png,pdf|max:10240']);
            $receiptPath = $request->file('receipt')->store("accounting/receipts/{$plan->id}", 'public');
        }

        $this->accountingService->markDownpaymentPaid($plan, $receiptPath);

        return back()->with('success', 'Downpayment marcado como pagado.');
    }

    public function markInstallmentPaid(Request $request, DesignerInstallment $installment)
    {
        $request->validate([
            'payment_method' => 'required|string|in:wire_transfer,venmo,zelle,cash,check,other',
            'payment_reference' => 'nullable|string|max:255',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'notes' => 'nullable|string',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $planId = $installment->payment_plan_id;
            $receiptPath = $request->file('receipt')->store("accounting/receipts/{$planId}", 'public');
        }

        $this->accountingService->markInstallmentPaid(
            $installment,
            $request->user()->id,
            $request->payment_method,
            $request->payment_reference,
            $receiptPath,
            $request->notes,
        );

        // Create PaymentRecord for audit trail
        $plan = $installment->paymentPlan;
        PaymentRecord::create([
            'designer_id' => $plan->designer_id,
            'event_id' => $plan->event_id,
            'amount' => $installment->amount,
            'payment_type' => 'installment',
            'payment_method' => $request->payment_method,
            'reference' => $request->payment_reference,
            'receipt_url' => $receiptPath,
            'notes' => $request->notes,
            'registered_by' => $request->user()->id,
            'payment_date' => now(),
        ]);

        return back()->with('success', 'Cuota marcada como pagada.');
    }

    public function updateDesignerInfo(Request $request, User $designer, Event $event)
    {
        abort_unless($designer->role === 'designer', 404);

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $designer->id,
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive,pending',
            'brand_name' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:designer_categories,id',
            'sales_rep_id' => 'nullable|exists:users,id',
            'looks' => 'nullable|integer|min:0',
            'package_id' => 'nullable|exists:designer_packages,id',
        ]);

        $oldStatus = $designer->status;
        $designer->update($request->only(['first_name', 'last_name', 'email', 'phone', 'status']));

        if ($request->filled('status') && $request->status === 'active' && $oldStatus !== 'active') {
            $salesUsers = User::where('role', 'sales')->where('status', 'active')->get();
            $designer->loadMissing('designerProfile');
            foreach ($salesUsers as $salesUser) {
                $salesUser->notify(new DesignerStatusChanged($designer, 'active'));
            }
        }

        $designer->designerProfile?->update($request->only(['brand_name', 'category_id', 'sales_rep_id']));

        $pivotData = ['looks' => $request->looks];
        $newPackagePrice = null;
        if ($request->filled('package_id')) {
            $package = DesignerPackage::find($request->package_id);
            $pivotData['package_id'] = $package->id;
            $pivotData['package_price'] = $package->price;
            $newPackagePrice = (float) $package->price;
        }

        // Actualizar pivot si existe en event_designer, o sales_registration como fallback
        $eventDesigner = $designer->eventsAsDesigner()->where('events.id', $event->id)->first();
        if ($eventDesigner) {
            $designer->eventsAsDesigner()->updateExistingPivot($event->id, $pivotData);
        } else {
            $salesReg = SalesRegistration::where('designer_id', $designer->id)->where('event_id', $event->id)->first();
            if ($salesReg) {
                $updateData = [];
                if (isset($pivotData['package_id'])) $updateData['package_id'] = $pivotData['package_id'];
                if (isset($pivotData['package_price'])) $updateData['agreed_price'] = $pivotData['package_price'];
                if ($updateData) $salesReg->update($updateData);
            }
        }

        // Si cambió el paquete y existe un plan, validar y recalcular
        if ($newPackagePrice !== null) {
            $plan = DesignerPaymentPlan::where('designer_id', $designer->id)
                ->where('event_id', $event->id)
                ->first();

            if ($plan && (float) $plan->total_amount !== $newPackagePrice) {
                // Block downgrade if total paid exceeds new package price
                $totalPaid = $plan->totalPaid();
                if ($totalPaid > $newPackagePrice) {
                    return back()->withErrors([
                        'package_id' => "Cannot change to this package. The designer has already paid \${$totalPaid} which exceeds the new package price of \${$newPackagePrice}. Contact accounting to process a refund first.",
                    ]);
                }

                $this->accountingService->updatePaymentPlan(
                    $plan,
                    $newPackagePrice,
                    (float) $plan->downpayment,
                    $plan->installments_count,
                    $plan->notes,
                );
            }
        }

        return back()->with('success', 'Datos del diseñador actualizados.');
    }

    public function uploadReceipt(Request $request, DesignerInstallment $installment)
    {
        $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $planId = $installment->payment_plan_id;
        $path = $request->file('receipt')->store("accounting/receipts/{$planId}", 'public');
        $installment->update(['receipt_url' => $path]);

        return back()->with('success', 'Comprobante subido.');
    }

    // =============================================
    // REGISTRO DE PAGOS (Payment Records)
    // =============================================

    public function paymentRecords(Request $request): Response
    {
        $query = PaymentRecord::with(['designer.designerProfile', 'event', 'registeredBy']);

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }
        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'ilike', "%{$search}%")
                    ->orWhereHas('designer', function ($sub) use ($search) {
                        $sub->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$search}%"));
                    });
            });
        }
        if ($request->filled('date_from')) {
            $query->where('payment_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('payment_date', '<=', $request->date_to . ' 23:59:59');
        }

        // Totals from filtered query (before pagination)
        $totalsQuery = clone $query;
        $totals = [
            'total' => (float) $totalsQuery->sum('amount'),
            'downpayments' => (float) (clone $totalsQuery)->where('payment_type', 'downpayment')->sum('amount'),
            'installments' => (float) (clone $totalsQuery)->where('payment_type', 'installment')->sum('amount'),
            'count' => $totalsQuery->count(),
        ];

        $records = $query->orderBy('payment_date', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(fn(PaymentRecord $r) => [
                'id' => $r->id,
                'amount' => (float) $r->amount,
                'payment_type' => $r->payment_type,
                'payment_type_label' => $r->payment_type_label,
                'payment_method' => $r->payment_method,
                'payment_method_label' => $r->payment_method_label,
                'reference' => $r->reference,
                'receipt_url' => $r->receipt_url,
                'notes' => $r->notes,
                'payment_date' => $r->payment_date->format('Y-m-d\TH:i'),
                'payment_date_formatted' => $r->payment_date->translatedFormat('d M Y - g:i A'),
                'designer_id' => $r->designer_id,
                'designer_name' => $r->designer->first_name . ' ' . $r->designer->last_name,
                'designer_brand' => $r->designer->designerProfile?->brand_name,
                'event_id' => $r->event_id,
                'event_name' => $r->event->name,
                'registered_by' => $r->registeredBy->first_name . ' ' . $r->registeredBy->last_name,
            ]);

        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        return Inertia::render('Admin/Accounting/PaymentRecords', [
            'records' => $records,
            'events' => $events,
            'totals' => $totals,
            'filters' => $request->only(['event_id', 'payment_type', 'payment_method', 'search', 'date_from', 'date_to']),
        ]);
    }

    public function storePaymentRecord(Request $request)
    {
        $request->validate([
            'designer_id' => 'required|exists:users,id',
            'event_id' => 'required|exists:events,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:downpayment,installment',
            'payment_method' => 'required|string|in:wire_transfer,venmo,zelle,stripe,cash,check,other',
            'reference' => 'nullable|string|max:255',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'notes' => 'nullable|string',
            'payment_date' => 'required|date',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('accounting/payment-records', 'public');
        }

        PaymentRecord::create([
            'designer_id' => $request->designer_id,
            'event_id' => $request->event_id,
            'amount' => $request->amount,
            'payment_type' => $request->payment_type,
            'payment_method' => $request->payment_method,
            'reference' => $request->reference,
            'receipt_url' => $receiptPath,
            'notes' => $request->notes,
            'registered_by' => $request->user()->id,
            'payment_date' => $request->payment_date,
        ]);

        // Auto-allocate payment to installments or downpayment
        if ($request->payment_type === 'installment') {
            $this->accountingService->allocatePaymentToInstallments(
                $request->designer_id,
                $request->event_id,
                (float) $request->amount,
                $request->user()->id,
                $request->payment_method,
                $request->reference,
            );
        } elseif ($request->payment_type === 'downpayment') {
            $this->accountingService->allocateDownpayment(
                $request->designer_id,
                $request->event_id,
            );
        }

        return back()->with('success', 'Pago registrado exitosamente.');
    }

    public function updatePaymentRecord(Request $request, PaymentRecord $record)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:downpayment,installment',
            'payment_method' => 'required|string|in:wire_transfer,venmo,zelle,stripe,cash,check,other',
            'reference' => 'nullable|string|max:255',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'notes' => 'nullable|string',
            'payment_date' => 'required|date',
        ]);

        // Save old values before update for reversal
        $oldAmount = (float) $record->amount;
        $oldType = $record->payment_type;
        $designerId = $record->designer_id;
        $eventId = $record->event_id;

        $data = $request->only(['amount', 'payment_type', 'payment_method', 'reference', 'notes', 'payment_date']);

        if ($request->hasFile('receipt')) {
            if ($record->receipt_url) {
                Storage::disk('public')->delete($record->receipt_url);
            }
            $data['receipt_url'] = $request->file('receipt')->store('accounting/payment-records', 'public');
        }

        // Reverse old allocation
        if ($oldType === 'installment') {
            $this->accountingService->reversePaymentAllocation($designerId, $eventId, $oldAmount);
        } elseif ($oldType === 'downpayment') {
            $this->accountingService->reverseDownpayment($designerId, $eventId);
        }

        $record->update($data);

        // Re-allocate with new values
        if ($data['payment_type'] === 'installment') {
            $this->accountingService->allocatePaymentToInstallments(
                $designerId,
                $eventId,
                (float) $data['amount'],
                $request->user()->id,
                $data['payment_method'],
                $data['reference'] ?? null,
            );
        } elseif ($data['payment_type'] === 'downpayment') {
            $this->accountingService->allocateDownpayment($designerId, $eventId);
        }

        return back()->with('success', 'Registro actualizado.');
    }

    public function destroyPaymentRecord(PaymentRecord $record)
    {
        // Reverse allocation before deleting
        if ($record->payment_type === 'installment') {
            $this->accountingService->reversePaymentAllocation(
                $record->designer_id,
                $record->event_id,
                (float) $record->amount,
            );
        } elseif ($record->payment_type === 'downpayment') {
            $this->accountingService->reverseDownpayment(
                $record->designer_id,
                $record->event_id,
            );
        }

        if ($record->receipt_url) {
            Storage::disk('public')->delete($record->receipt_url);
        }

        $record->delete();

        return back()->with('success', 'Registro eliminado.');
    }

    // =============================================
    // LISTA DE DISEÑADORES (Designers List)
    // =============================================

    public function designersList(Request $request): Response
    {
        $query = User::where('role', 'designer')
            ->with(['designerProfile.category', 'designerProfile.salesRep']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('event_id')) {
            $eventId = (int) $request->event_id;
            $query->where(function ($q) use ($eventId) {
                $q->whereHas('eventsAsDesigner', fn($eq) => $eq->where('events.id', $eventId))
                  ->orWhereHas('salesRegistrations', fn($sq) => $sq->where('event_id', $eventId));
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhereHas('designerProfile', fn($q2) => $q2->where('brand_name', 'ilike', "%{$search}%"));
            });
        }

        $filteredEventId = $request->filled('event_id') ? (int) $request->event_id : null;

        $designers = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $designers->getCollection()->transform(function ($designer) use ($filteredEventId) {
            $edQuery = DB::table('event_designer')
                ->where('designer_id', $designer->id);

            if ($filteredEventId) {
                $edQuery->where('event_id', $filteredEventId);
            } else {
                $edQuery->orderByDesc('created_at');
            }

            $eventDesigner = $edQuery->first();

            $paymentPlan = DesignerPaymentPlan::where('designer_id', $designer->id)
                ->when($filteredEventId, fn($q) => $q->where('event_id', $filteredEventId))
                ->with('installments')
                ->orderByDesc('created_at')
                ->first();

            $package = $eventDesigner && $eventDesigner->package_id
                ? DesignerPackage::find($eventDesigner->package_id)
                : null;

            $event = $eventDesigner ? Event::find($eventDesigner->event_id) : null;

            // Fallback: si no hay event_designer, buscar en sales_registrations
            if (!$eventDesigner) {
                $salesReg = SalesRegistration::where('designer_id', $designer->id)
                    ->when($filteredEventId, fn($q) => $q->where('event_id', $filteredEventId))
                    ->orderByDesc('created_at')
                    ->first();

                if ($salesReg) {
                    $event = Event::find($salesReg->event_id);
                    $package = $salesReg->package_id ? DesignerPackage::find($salesReg->package_id) : null;
                }
            }

            $designer->current_event = $event;
            $designer->event_name = $event?->name;
            $designer->current_package = $package;
            $designer->package_price = $eventDesigner->package_price
                ?? (isset($salesReg) && $salesReg ? $salesReg->agreed_price : null)
                ?? ($package->price ?? 0);
            $designer->amount_pending = $paymentPlan ? $paymentPlan->totalPending() : (float) ($designer->package_price ?? 0);
            $designer->amount_paid = $paymentPlan ? $paymentPlan->totalPaid() : 0;

            return $designer;
        });

        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        return Inertia::render('Admin/Accounting/DesignersList', [
            'designers' => $designers,
            'events' => $events,
            'filters' => $request->only(['status', 'search', 'event_id']),
        ]);
    }

    public function designerDetail(User $designer): JsonResponse
    {
        abort_unless($designer->role === 'designer', 404);

        $designer->load(['designerProfile.category', 'designerProfile.salesRep']);

        $eventDesigner = DB::table('event_designer')
            ->where('designer_id', $designer->id)
            ->orderByDesc('created_at')
            ->first();

        $event = $eventDesigner ? Event::find($eventDesigner->event_id) : null;
        $package = $eventDesigner && $eventDesigner->package_id
            ? DesignerPackage::find($eventDesigner->package_id)
            : null;

        // Fallback: buscar en sales_registrations
        $salesReg = null;
        if (!$eventDesigner) {
            $salesReg = SalesRegistration::where('designer_id', $designer->id)
                ->orderByDesc('created_at')
                ->first();
            if ($salesReg) {
                $event = Event::find($salesReg->event_id);
                $package = $salesReg->package_id ? DesignerPackage::find($salesReg->package_id) : null;
            }
        }

        $paymentPlan = DesignerPaymentPlan::where('designer_id', $designer->id)
            ->with('installments')
            ->orderByDesc('created_at')
            ->first();

        $packagePrice = $eventDesigner->package_price ?? ($salesReg?->agreed_price ?? 0);

        return response()->json([
            'designer' => [
                'id' => $designer->id,
                'first_name' => $designer->first_name,
                'last_name' => $designer->last_name,
                'email' => $designer->email,
                'phone' => $designer->phone,
                'status' => $designer->status,
                'profile_picture' => $designer->profile_picture,
                'brand_name' => $designer->designerProfile?->brand_name,
                'category' => $designer->designerProfile?->category?->name,
                'country' => $designer->designerProfile?->country,
                'bio' => $designer->designerProfile?->bio,
                'social_media' => $designer->designerProfile?->social_media,
                'sales_rep' => $designer->designerProfile?->salesRep ? [
                    'name' => $designer->designerProfile->salesRep->first_name . ' ' . $designer->designerProfile->salesRep->last_name,
                ] : null,
            ],
            'event' => $event ? [
                'id' => $event->id,
                'name' => $event->name,
                'looks' => $eventDesigner->looks ?? 0,
                'package_price' => $packagePrice,
                'model_casting_enabled' => (bool) ($eventDesigner->model_casting_enabled ?? false),
                'media_package'         => (bool) ($eventDesigner->media_package ?? false),
                'custom_background'     => (bool) ($eventDesigner->custom_background ?? false),
                'courtesy_tickets'      => (bool) ($eventDesigner->courtesy_tickets ?? false),
            ] : null,
            'package' => $package ? ['id' => $package->id, 'name' => $package->name, 'price' => (float) $package->price] : null,
            'payment_plan' => $paymentPlan ? [
                'id' => $paymentPlan->id,
                'total_amount' => (float) $paymentPlan->total_amount,
                'downpayment' => (float) $paymentPlan->downpayment,
                'downpayment_status' => $paymentPlan->downpayment_status,
                'status' => $paymentPlan->status,
                'total_paid' => $paymentPlan->totalPaid(),
                'total_pending' => $paymentPlan->totalPending(),
                'progress' => $paymentPlan->progressPercentage(),
                'installments' => $paymentPlan->installments->map(fn($i) => [
                    'id' => $i->id,
                    'number' => $i->installment_number,
                    'amount' => (float) $i->amount,
                    'paid_amount' => (float) $i->paid_amount,
                    'due_date' => $i->due_date->format('Y-m-d'),
                    'status' => $i->isOverdue() ? 'overdue' : $i->status,
                    'payment_method' => $i->payment_method,
                ])->values(),
            ] : null,
        ]);
    }

    public function exportDesignersList(Request $request)
    {
        $query = User::where('role', 'designer')
            ->with(['designerProfile.category', 'designerProfile.salesRep']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $filteredEventId = $request->filled('event_id') ? (int) $request->event_id : null;

        if ($filteredEventId) {
            $query->where(function ($q) use ($filteredEventId) {
                $q->whereHas('eventsAsDesigner', fn($eq) => $eq->where('events.id', $filteredEventId))
                  ->orWhereHas('salesRegistrations', fn($sq) => $sq->where('event_id', $filteredEventId));
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhereHas('designerProfile', fn($q2) => $q2->where('brand_name', 'ilike', "%{$search}%"));
            });
        }

        $designers = $query->orderBy('first_name')->get();

        $csv = "Marca,Diseñador,Evento,Representante de Ventas,Paquete,Monto Paquete,Monto Pendiente,Estado\n";

        foreach ($designers as $designer) {
            $edQuery = DB::table('event_designer')
                ->where('designer_id', $designer->id);

            if ($filteredEventId) {
                $edQuery->where('event_id', $filteredEventId);
            } else {
                $edQuery->orderByDesc('created_at');
            }

            $eventDesigner = $edQuery->first();

            $package = $eventDesigner && $eventDesigner->package_id
                ? DesignerPackage::find($eventDesigner->package_id)
                : null;

            $event = $eventDesigner ? Event::find($eventDesigner->event_id) : null;

            // Fallback a sales_registrations
            $salesRegExport = null;
            if (!$eventDesigner) {
                $salesRegExport = SalesRegistration::where('designer_id', $designer->id)
                    ->when($filteredEventId, fn($q) => $q->where('event_id', $filteredEventId))
                    ->orderByDesc('created_at')
                    ->first();
                if ($salesRegExport) {
                    $event = Event::find($salesRegExport->event_id);
                    $package = $salesRegExport->package_id ? DesignerPackage::find($salesRegExport->package_id) : null;
                }
            }

            $paymentPlan = DesignerPaymentPlan::where('designer_id', $designer->id)
                ->when($filteredEventId, fn($q) => $q->where('event_id', $filteredEventId))
                ->first();

            $brand = str_replace('"', '""', $designer->designerProfile->brand_name ?? '-');
            $name = str_replace('"', '""', $designer->first_name . ' ' . $designer->last_name);
            $eventName = str_replace('"', '""', $event?->name ?? '-');
            $salesRep = $designer->designerProfile->salesRep
                ? str_replace('"', '""', $designer->designerProfile->salesRep->first_name . ' ' . $designer->designerProfile->salesRep->last_name)
                : '-';
            $packageName = $package->name ?? '-';
            $packagePrice = $eventDesigner->package_price ?? ($salesRegExport?->agreed_price ?? ($package->price ?? 0));
            $pending = $paymentPlan ? $paymentPlan->totalPending() : (float) $packagePrice;
            $status = match($designer->status) {
                'active' => 'Activo',
                'inactive' => 'Inactivo',
                'pending' => 'Pendiente',
                default => $designer->status,
            };

            $csv .= "\"{$brand}\",\"{$name}\",\"{$eventName}\",\"{$salesRep}\",\"{$packageName}\",\${$packagePrice},\${$pending},{$status}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="designers_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    // =============================================
    // DEUDAS (Overdue List)
    // =============================================

    public function overdueList(Request $request): Response
    {
        // Update overdue statuses before showing the list
        $this->accountingService->updateOverdueInstallments();

        // Query plans that have overdue installments (active designers only)
        $query = DesignerPaymentPlan::whereHas('installments', function ($q) {
                $q->whereIn('status', ['overdue', 'partial'])->where('due_date', '<', now());
            })
            ->whereHas('designer', fn($q) => $q->where('status', 'active'))
            ->with(['designer.designerProfile.salesRep', 'event', 'package', 'installments']);

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('designer', function ($q) use ($search) {
                $q->where('status', 'active')
                    ->where(function ($q) use ($search) {
                        $q->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$search}%"));
                    });
            });
        }

        // Stats from full filtered set (before pagination)
        $allPlans = (clone $query)->get();
        $totalOverdueAmount = 0;
        $overdueInstallmentsCount = 0;
        $oldestOverdue = null;

        foreach ($allPlans as $plan) {
            $overdueInsts = $plan->installments->filter(fn($i) => in_array($i->status, ['overdue', 'partial']) && $i->due_date->isPast());
            foreach ($overdueInsts as $inst) {
                $totalOverdueAmount += (float) $inst->amount - (float) $inst->paid_amount;
                $overdueInstallmentsCount++;
                if (!$oldestOverdue || $inst->due_date < $oldestOverdue) {
                    $oldestOverdue = $inst->due_date;
                }
            }
        }

        $plans = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(function (DesignerPaymentPlan $plan) {
                $designer = $plan->designer;
                $overdueInsts = $plan->installments->filter(fn($i) => in_array($i->status, ['overdue', 'partial']) && $i->due_date->isPast());

                $overdueCount = $overdueInsts->count();
                $overdueAmount = $overdueInsts->sum(fn($i) => (float) $i->amount - (float) $i->paid_amount);
                $oldestDue = $overdueInsts->min('due_date');
                $maxDaysOverdue = $oldestDue ? abs((int) now()->diffInDays($oldestDue)) : 0;

                $salesRep = $designer->designerProfile?->salesRep;

                return [
                    'plan_id' => $plan->id,
                    'designer_id' => $designer->id,
                    'designer_name' => $designer->first_name . ' ' . $designer->last_name,
                    'brand_name' => $designer->designerProfile?->brand_name,
                    'event_id' => $plan->event_id,
                    'event_name' => $plan->event?->name,
                    'package_name' => $plan->package?->name ?? 'Sin paquete',
                    'sales_rep' => $salesRep ? $salesRep->first_name . ' ' . $salesRep->last_name : null,
                    'overdue_count' => $overdueCount,
                    'overdue_amount' => round($overdueAmount, 2),
                    'max_days_overdue' => $maxDaysOverdue,
                ];
            });

        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        return Inertia::render('Admin/Accounting/OverdueList', [
            'plans' => $plans,
            'events' => $events,
            'stats' => [
                'total_overdue_amount' => round($totalOverdueAmount, 2),
                'designers_with_overdue' => $allPlans->pluck('designer_id')->unique()->count(),
                'overdue_installments_count' => $overdueInstallmentsCount,
                'oldest_overdue' => $oldestOverdue?->format('Y-m-d'),
            ],
            'filters' => $request->only(['event_id', 'search']),
        ]);
    }

    public function exportOverdueList(Request $request)
    {
        $query = DesignerPaymentPlan::whereHas('installments', function ($q) {
                $q->whereIn('status', ['overdue', 'partial'])->where('due_date', '<', now());
            })
            ->whereHas('designer', fn($q) => $q->where('status', 'active'))
            ->with(['designer.designerProfile.salesRep', 'event', 'package', 'installments']);

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('designer', function ($q) use ($search) {
                $q->where('status', 'active')
                    ->where(function ($q) use ($search) {
                        $q->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$search}%"));
                    });
            });
        }

        $plans = $query->orderBy('created_at', 'desc')->get();

        $csv = "Marca,Diseñador,Evento,Rep. Ventas,Paquete,Cuotas Vencidas,Monto Pendiente,Días Vencido\n";

        foreach ($plans as $plan) {
            $designer = $plan->designer;
            $overdueInsts = $plan->installments->filter(fn($i) => in_array($i->status, ['overdue', 'partial']) && $i->due_date->isPast());

            $overdueAmount = $overdueInsts->sum(fn($i) => (float) $i->amount - (float) $i->paid_amount);
            $oldestDue = $overdueInsts->min('due_date');
            $maxDays = $oldestDue ? abs((int) now()->diffInDays($oldestDue)) : 0;

            $brand = str_replace('"', '""', $designer->designerProfile?->brand_name ?? '—');
            $name = str_replace('"', '""', $designer->first_name . ' ' . $designer->last_name);
            $eventName = str_replace('"', '""', $plan->event?->name ?? '—');
            $salesRep = $designer->designerProfile?->salesRep;
            $salesRepName = $salesRep ? str_replace('"', '""', $salesRep->first_name . ' ' . $salesRep->last_name) : '—';
            $packageName = str_replace('"', '""', $plan->package?->name ?? 'Sin paquete');

            $csv .= "\"{$brand}\",\"{$name}\",\"{$eventName}\",\"{$salesRepName}\",\"{$packageName}\",{$overdueInsts->count()},\$" . round($overdueAmount, 2) . ",{$maxDays}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="deudas_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    public function searchDesignersForRecord(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'query' => 'nullable|string',
        ]);

        $event = Event::findOrFail($request->event_id);
        $search = $request->get('query', '');

        // Diseñadores asignados al evento (event_designer)
        $designers = $event->designers()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$search}%"));
                });
            })
            ->with('designerProfile')
            ->limit(15)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->first_name . ' ' . $d->last_name,
                'brand' => $d->designerProfile?->brand_name,
            ]);

        // Diseñadores de sales_registrations no asignados aún
        $assignedIds = $designers->pluck('id');
        $salesDesigners = SalesRegistration::where('event_id', $event->id)
            ->whereNotIn('designer_id', $assignedIds)
            ->whereHas('designer', function ($q) use ($search) {
                if ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$search}%"));
                    });
                }
            })
            ->with('designer.designerProfile')
            ->limit(15)
            ->get()
            ->map(fn($reg) => [
                'id' => $reg->designer->id,
                'name' => $reg->designer->first_name . ' ' . $reg->designer->last_name,
                'brand' => $reg->designer->designerProfile?->brand_name,
            ]);

        return response()->json($designers->concat($salesDesigners)->values());
    }

    // =============================================
    // HISTORIAL / BITÁCORA (Support Cases)
    // =============================================

    public function caseHistory(Request $request): Response
    {
        $query = SupportCase::with(['designer.designerProfile', 'event', 'latestMessage'])
            ->withCount('messages');

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }
        if ($request->filled('case_type')) {
            $query->where('case_type', $request->case_type);
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'ilike', "%{$search}%")
                    ->orWhereHas('designer', function ($sub) use ($search) {
                        $sub->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$search}%"));
                    });
            });
        }

        $totalCount = (clone $query)->count();

        $cases = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(fn(SupportCase $case) => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'designer_name' => $case->designer->first_name . ' ' . $case->designer->last_name,
                'brand_name' => $case->designer->designerProfile?->brand_name,
                'event_name' => $case->event?->name,
                'channel' => $case->channel,
                'channel_label' => $case->channel_label,
                'case_type' => $case->case_type,
                'case_type_label' => $case->case_type_label,
                'claim_date' => $case->claim_date->format('Y-m-d'),
                'status' => $case->status,
                'status_label' => $case->status_label,
                'messages_count' => $case->messages_count,
                'last_message_date' => $case->latestMessage?->message_date?->format('Y-m-d'),
            ]);

        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        return Inertia::render('Admin/Accounting/CaseHistory', [
            'cases' => $cases,
            'events' => $events,
            'totalCount' => $totalCount,
            'filters' => $request->only(['event_id', 'case_type', 'channel', 'status', 'search']),
        ]);
    }

    public function createCase(Request $request): Response
    {
        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);
        $teamMembers = User::whereIn('role', ['accounting', 'admin'])
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'role']);
        $nextCaseNumber = SupportCase::generateCaseNumber();

        return Inertia::render('Admin/Accounting/CaseCreate', [
            'events' => $events,
            'teamMembers' => $teamMembers,
            'nextCaseNumber' => $nextCaseNumber,
        ]);
    }

    public function storeCase(Request $request): RedirectResponse
    {
        $request->validate([
            'designer_id' => 'required|exists:users,id',
            'event_id' => 'nullable|exists:events,id',
            'channel' => 'required|in:email,sms,phone,whatsapp,dm',
            'case_type' => 'required|in:claim,complaint,payment,refund',
            'contact_email' => 'nullable|email|max:255',
            'save_email' => 'nullable|boolean',
            'claim_date' => 'required|date',
            'message' => 'required|string',
            'message_date' => 'required|date',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:20480',
            // Optional team response
            'team_response' => 'nullable|boolean',
            'team_member_id' => 'nullable|exists:users,id',
            'team_message' => 'nullable|string',
            'team_message_date' => 'nullable|date',
            'team_attachments' => 'nullable|array',
            'team_attachments.*' => 'file|max:20480',
        ]);

        $case = SupportCase::create([
            'case_number' => SupportCase::generateCaseNumber(),
            'designer_id' => $request->designer_id,
            'event_id' => $request->event_id,
            'channel' => $request->channel,
            'case_type' => $request->case_type,
            'contact_email' => $request->contact_email,
            'claim_date' => $request->claim_date,
            'status' => 'open',
            'created_by' => $request->user()->id,
        ]);

        // First message (designer)
        $designerMsg = $case->messages()->create([
            'sender_type' => 'designer',
            'message' => $request->message,
            'message_date' => $request->message_date,
        ]);

        $this->storeAttachments($designerMsg, $request->file('attachments'), $case->id);

        // Optional team response
        if ($request->boolean('team_response') && $request->filled('team_message')) {
            $teamMsg = $case->messages()->create([
                'sender_type' => 'team',
                'team_member_id' => $request->team_member_id,
                'message' => $request->team_message,
                'message_date' => $request->team_message_date ?? now()->toDateString(),
            ]);

            $this->storeAttachments($teamMsg, $request->file('team_attachments'), $case->id);
            $case->update(['status' => 'in_progress']);
        }

        // Save contact email if requested
        if ($request->boolean('save_email') && $request->filled('contact_email')) {
            DesignerContactEmail::firstOrCreate(
                ['designer_id' => $request->designer_id, 'email' => $request->contact_email],
            );
        }

        return redirect()->route('admin.accounting.cases.show', $case->id)
            ->with('success', 'Caso creado exitosamente.');
    }

    public function showCase(SupportCase $case): Response
    {
        $case->load(['designer.designerProfile', 'event', 'createdBy', 'messages.teamMember', 'messages.attachments']);

        $teamMembers = User::whereIn('role', ['accounting', 'admin'])
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'role']);

        $designerEmails = $this->getDesignerEmailsList($case->designer_id);

        return Inertia::render('Admin/Accounting/CaseShow', [
            'case' => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'designer_id' => $case->designer_id,
                'designer_name' => $case->designer->first_name . ' ' . $case->designer->last_name,
                'brand_name' => $case->designer->designerProfile?->brand_name,
                'event_name' => $case->event?->name,
                'channel' => $case->channel,
                'channel_label' => $case->channel_label,
                'case_type' => $case->case_type,
                'case_type_label' => $case->case_type_label,
                'contact_email' => $case->contact_email,
                'claim_date' => $case->claim_date->format('Y-m-d'),
                'status' => $case->status,
                'status_label' => $case->status_label,
                'created_by' => $case->createdBy->first_name . ' ' . $case->createdBy->last_name,
                'created_at' => $case->created_at->format('Y-m-d H:i'),
                'messages' => $case->messages->map(fn(SupportCaseMessage $msg) => [
                    'id' => $msg->id,
                    'sender_type' => $msg->sender_type,
                    'team_member_name' => $msg->teamMember ? $msg->teamMember->first_name . ' ' . $msg->teamMember->last_name : null,
                    'message' => $msg->message,
                    'message_date' => $msg->message_date->format('Y-m-d'),
                    'created_at' => $msg->created_at->format('Y-m-d H:i'),
                    'attachments' => $msg->attachments->map(fn(SupportCaseAttachment $att) => [
                        'id' => $att->id,
                        'file_url' => $att->file_url,
                        'file_name' => $att->file_name,
                        'file_type' => $att->file_type,
                        'file_size' => $att->file_size,
                        'is_image' => str_starts_with($att->file_type, 'image/'),
                    ])->values(),
                ])->values(),
            ],
            'teamMembers' => $teamMembers,
            'designerEmails' => $designerEmails,
        ]);
    }

    public function addMessage(Request $request, SupportCase $case): RedirectResponse
    {
        $request->validate([
            'sender_type' => 'required|in:designer,team',
            'team_member_id' => 'nullable|exists:users,id',
            'message' => 'required|string',
            'message_date' => 'required|date',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:20480',
        ]);

        $msg = $case->messages()->create([
            'sender_type' => $request->sender_type,
            'team_member_id' => $request->sender_type === 'team' ? $request->team_member_id : null,
            'message' => $request->message,
            'message_date' => $request->message_date,
        ]);

        $this->storeAttachments($msg, $request->file('attachments'), $case->id);

        // Auto-update status
        if ($case->status === 'open' && $request->sender_type === 'team') {
            $case->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Mensaje agregado.');
    }

    public function updateCaseStatus(Request $request, SupportCase $case): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $case->update(['status' => $request->status]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function destroyCase(SupportCase $case): RedirectResponse
    {
        // Delete all attachment files from storage
        $case->load('messages.attachments');
        foreach ($case->messages as $msg) {
            foreach ($msg->attachments as $att) {
                Storage::disk('public')->delete($att->file_url);
            }
        }

        $case->delete();

        return redirect()->route('admin.accounting.cases.index')
            ->with('success', 'Caso eliminado.');
    }

    public function getDesignerEmails(User $designer): JsonResponse
    {
        return response()->json($this->getDesignerEmailsList($designer->id));
    }

    private function getDesignerEmailsList(int $designerId): array
    {
        $user = User::find($designerId);
        if (!$user) return [];

        $emails = [['email' => $user->email, 'label' => 'Login']];

        $contactEmails = DesignerContactEmail::where('designer_id', $designerId)
            ->orderBy('created_at')
            ->get();

        foreach ($contactEmails as $ce) {
            $emails[] = ['email' => $ce->email, 'label' => $ce->label ?? 'Contacto'];
        }

        return $emails;
    }

    // =============================================
    // REPORTE DE LIQUIDEZ (Liquidity Report)
    // =============================================

    public function liquidityReport(Request $request): Response
    {
        $defaultStart = now()->addMonth()->startOfMonth()->format('Y-m-d');
        $defaultEnd = now()->addMonth()->endOfMonth()->format('Y-m-d');

        $dateFrom = $request->input('date_from', $defaultStart);
        $dateTo = $request->input('date_to', $defaultEnd);
        $statusFilter = $request->input('status', ''); // '', 'overdue', 'pending'

        $query = DesignerInstallment::whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereHas('paymentPlan', function ($q) {
                $q->where('status', '!=', 'cancelled');
            })
            ->with(['paymentPlan.designer.designerProfile', 'paymentPlan.event']);

        if ($request->filled('event_id')) {
            $query->whereHas('paymentPlan', function ($q) use ($request) {
                $q->where('event_id', $request->event_id);
            });
        }

        // Date range is the primary filter — only installments within range
        $today = now()->startOfDay();
        $query->whereBetween('due_date', [$dateFrom, $dateTo]);

        // Status filter within the range
        if ($statusFilter === 'overdue') {
            $query->where('due_date', '<', $today);
        } elseif ($statusFilter === 'pending') {
            $query->where('due_date', '>=', $today);
        }

        $allInstallments = $query->get();

        $grouped = $allInstallments->groupBy(fn($inst) => $inst->due_date->format('Y-m-d'))
            ->map(function ($group, $date) {
                $totalAmount = $group->sum('amount');
                $totalPaid = $group->sum('paid_amount');
                $totalPending = $totalAmount - $totalPaid;
                $isOverdue = Carbon::parse($date)->lt(now()->startOfDay());

                $designers = $group->map(function ($inst) {
                    $plan = $inst->paymentPlan;
                    $designer = $plan->designer;
                    return [
                        'designer_id' => $designer->id,
                        'designer_name' => $designer->first_name . ' ' . $designer->last_name,
                        'brand_name' => $designer->designerProfile?->brand_name ?? '—',
                        'event_name' => $plan->event?->name ?? '—',
                        'installment_number' => $inst->installment_number,
                        'amount' => (float) $inst->amount,
                        'paid_amount' => (float) $inst->paid_amount,
                        'pending' => (float) $inst->amount - (float) $inst->paid_amount,
                        'status' => $inst->status,
                    ];
                })->values();

                return [
                    'date' => $date,
                    'total_amount' => round($totalAmount, 2),
                    'total_paid' => round($totalPaid, 2),
                    'total_pending' => round($totalPending, 2),
                    'designers_count' => $group->unique(fn($inst) => $inst->paymentPlan->designer_id)->count(),
                    'installments_count' => $group->count(),
                    'is_overdue' => $isOverdue,
                    'designers' => $designers,
                ];
            })->sortKeys()->values();

        $totalPendingAll = round($grouped->sum('total_pending'), 2);
        $totalOverdue = round($grouped->where('is_overdue', true)->sum('total_pending'), 2);
        $totalUpcoming = round($grouped->where('is_overdue', false)->sum('total_pending'), 2);

        $events = Event::orderByDesc('start_date')->get(['id', 'name']);

        return Inertia::render('Admin/Accounting/LiquidityReport', [
            'dates' => $grouped,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'event_id' => $request->input('event_id', ''),
                'status' => $statusFilter,
            ],
            'events' => $events,
            'totals' => [
                'total_pending' => $totalPendingAll,
                'total_overdue' => $totalOverdue,
                'total_upcoming' => $totalUpcoming,
                'total_dates' => $grouped->count(),
            ],
        ]);
    }

    public function exportLiquidityReport(Request $request)
    {
        $defaultStart = now()->addMonth()->startOfMonth()->format('Y-m-d');
        $defaultEnd = now()->addMonth()->endOfMonth()->format('Y-m-d');

        $dateFrom = $request->input('date_from', $defaultStart);
        $dateTo = $request->input('date_to', $defaultEnd);
        $statusFilter = $request->input('status', '');

        $query = DesignerInstallment::whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereHas('paymentPlan', fn($q) => $q->where('status', '!=', 'cancelled'))
            ->with(['paymentPlan.designer.designerProfile', 'paymentPlan.event']);

        if ($request->filled('event_id')) {
            $query->whereHas('paymentPlan', fn($q) => $q->where('event_id', $request->event_id));
        }

        $today = now()->startOfDay();
        $query->whereBetween('due_date', [$dateFrom, $dateTo]);

        if ($statusFilter === 'overdue') {
            $query->where('due_date', '<', $today);
        } elseif ($statusFilter === 'pending') {
            $query->where('due_date', '>=', $today);
        }

        $all = $query->get();
        $grouped = $all->groupBy(fn($i) => $i->due_date->format('Y-m-d'))->sortKeys();

        $csv = "REPORTE DE LIQUIDEZ\n";
        $csv .= "Rango: {$dateFrom} a {$dateTo}\n\n";
        $csv .= "Fecha,Monto Total,Monto Pendiente,Diseñadores,Cuotas,Estado\n";

        $grandTotal = 0;
        $grandPending = 0;

        foreach ($grouped as $date => $group) {
            $totalAmt = $group->sum('amount');
            $totalPaid = $group->sum('paid_amount');
            $pending = $totalAmt - $totalPaid;
            $designersCnt = $group->unique(fn($i) => $i->paymentPlan->designer_id)->count();
            $isOverdue = Carbon::parse($date)->lt($today);
            $estado = $isOverdue ? 'Vencido' : 'Pendiente';

            $csv .= "{$date},\$" . number_format($totalAmt, 2) . ",\$" . number_format($pending, 2) . ",{$designersCnt},{$group->count()},{$estado}\n";
            $grandTotal += $totalAmt;
            $grandPending += $pending;
        }

        $csv .= "TOTAL,\$" . number_format($grandTotal, 2) . ",\$" . number_format($grandPending, 2) . ",,, \n";

        $csv .= "\n\nDETALLE POR DISEÑADOR\n";
        $csv .= "Fecha,Diseñador,Marca,Evento,Cuota #,Monto,Pagado,Pendiente,Estado\n";

        foreach ($grouped as $date => $group) {
            foreach ($group as $inst) {
                $plan = $inst->paymentPlan;
                $d = $plan->designer;
                $name = str_replace('"', '""', $d->first_name . ' ' . $d->last_name);
                $brand = str_replace('"', '""', $d->designerProfile?->brand_name ?? '—');
                $event = str_replace('"', '""', $plan->event?->name ?? '—');
                $pending = (float) $inst->amount - (float) $inst->paid_amount;
                $estado = match($inst->status) {
                    'overdue' => 'Vencida',
                    'partial' => 'Parcial',
                    default => 'Pendiente',
                };

                $csv .= "{$date},\"{$name}\",\"{$brand}\",\"{$event}\",{$inst->installment_number},\$" . number_format($inst->amount, 2) . ",\$" . number_format($inst->paid_amount, 2) . ",\$" . number_format($pending, 2) . ",{$estado}\n";
            }
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="liquidez_' . $dateFrom . '_' . $dateTo . '.csv"',
        ]);
    }

    private function storeAttachments(SupportCaseMessage $message, ?array $files, int $caseId): void
    {
        if (!$files) return;

        foreach ($files as $file) {
            $fileName = $message->id . '_' . time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs("accounting/cases/{$caseId}", $fileName, 'public');

            $message->attachments()->create([
                'file_url' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }
}
