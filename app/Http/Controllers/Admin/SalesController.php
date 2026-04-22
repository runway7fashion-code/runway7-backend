<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\DesignerCategory;
use App\Models\DesignerLead;
use App\Models\DesignerPackage;
use App\Models\Event;
use App\Models\SalesDocument;
use App\Models\SalesRegistration;
use App\Models\User;
use App\Jobs\SendDesignerWelcomeSalesJob;
use App\Notifications\NewDesignerRegistered;
use App\Services\DesignerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function __construct(protected DesignerService $designerService) {}

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $isAsesor = $user->role === 'sales' && $user->sales_type !== 'lider';
        $isLider  = $user->role === 'sales' && $user->sales_type === 'lider';

        // Asesor solo ve lo suyo; líder y admin ven todo
        // whereHas('designer') excluye registrations de designers soft-deleted o huérfanos
        $baseQuery = fn() => SalesRegistration::query()
            ->whereHas('designer')
            ->when($isAsesor, fn($q) => $q->where('sales_rep_id', $user->id));

        $totalRegistrations = $baseQuery()->count();
        $confirmed = $baseQuery()->where('status', 'confirmed')->count();

        $stats = [
            'total_registrations' => $totalRegistrations,
            'registered'          => $baseQuery()->where('status', 'registered')->count(),
            'onboarded'           => $baseQuery()->where('status', 'onboarded')->count(),
            'confirmed'           => $confirmed,
            'cancelled'           => $baseQuery()->where('status', 'cancelled')->count(),
        ];

        $totalRevenue    = $baseQuery()->where('status', '!=', 'cancelled')->sum('agreed_price');
        $totalDownpayments = $baseQuery()->where('status', '!=', 'cancelled')->sum('downpayment');
        $thisMonthCount  = $baseQuery()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        $topSellers = null;
        if ($isLider || $user->role === 'admin') {
            $topSellersData = SalesRegistration::whereHas('designer')
                ->where('status', '!=', 'cancelled')
                ->selectRaw('sales_rep_id, count(*) as total')
                ->groupBy('sales_rep_id')
                ->orderByDesc('total')
                ->limit(3)
                ->get();

            $repIds = $topSellersData->pluck('sales_rep_id');
            $reps = User::whereIn('id', $repIds)->get(['id', 'first_name', 'last_name'])->keyBy('id');

            $topSellers = $topSellersData->map(fn($row) => [
                'name'  => $reps[$row->sales_rep_id]?->full_name ?? '—',
                'total' => $row->total,
            ])->values();
        }

        // Conversion rate = designers registered / total leads assigned
        $totalLeadsForUser = DesignerLead::whereNull('deleted_at')
            ->when($isAsesor, fn($q) => $q->where('assigned_to', $user->id))
            ->count();
        $conversionRate = $totalLeadsForUser > 0 ? round(($totalRegistrations / $totalLeadsForUser) * 100) : 0;

        $financeStats = [
            'total_revenue'      => (float) $totalRevenue,
            'total_downpayments' => (float) $totalDownpayments,
            'this_month_count'   => $thisMonthCount,
            'conversion_rate'    => $conversionRate,
            'total_leads'        => $totalLeadsForUser,
            'top_sellers'        => $topSellers,
        ];

        $recentRegistrations = $baseQuery()
            ->whereHas('designer')
            ->with(['designer:id,first_name,last_name,email', 'event:id,name', 'package:id,name', 'salesRep:id,first_name,last_name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Stats por asesor (solo para líder y admin)
        $salesRepStats = null;
        if ($isLider || $user->role === 'admin') {
            $salesRepStats = User::where('role', 'sales')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'sales_type'])
                ->map(function ($rep) {
                    $q = fn() => SalesRegistration::whereHas('designer')->where('sales_rep_id', $rep->id);
                    return [
                        'id'         => $rep->id,
                        'name'       => "{$rep->first_name} {$rep->last_name}",
                        'sales_type' => $rep->sales_type,
                        'total'      => $q()->count(),
                        'registered' => $q()->where('status', 'registered')->count(),
                        'onboarded'  => $q()->where('status', 'onboarded')->count(),
                        'confirmed'  => $q()->where('status', 'confirmed')->count(),
                        'cancelled'  => $q()->where('status', 'cancelled')->count(),
                    ];
                });
        }

        // Rep ranking for podium (only for asesor role)
        $year = now()->year;
        $repRanking = collect();
        if ($isAsesor) $repRanking = User::where('role', 'sales')->where('sales_type', 'asesor')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'sales_type'])
            ->map(function ($rep) use ($year) {
                $q = fn() => SalesRegistration::whereHas('designer')
                    ->whereYear('sales_registrations.created_at', $year)
                    ->where('sales_rep_id', $rep->id);

                $total     = $q()->count();
                $confirmed = $q()->where('status', 'confirmed')->count();
                $cancelled = $q()->where('status', 'cancelled')->count();
                $revenue   = (float) $q()->where('status', '!=', 'cancelled')->sum('agreed_price');

                $totalLeads = DesignerLead::where('assigned_to', $rep->id)->whereNull('deleted_at')->count();

                return [
                    'id'              => $rep->id,
                    'name'            => $rep->full_name,
                    'sales_type'      => $rep->sales_type,
                    'total'           => $total,
                    'confirmed'       => $confirmed,
                    'cancelled'       => $cancelled,
                    'revenue'         => $revenue,
                    'total_leads'     => $totalLeads,
                    'conversion_rate' => $totalLeads > 0 ? round(($total / $totalLeads) * 100) : 0,
                ];
            })
            ->filter(fn($r) => $r['total'] > 0 || $r['total_leads'] > 0)
            ->sortByDesc('total')
            ->values();

        return Inertia::render('Admin/Sales/Dashboard', [
            'stats'               => $stats,
            'financeStats'        => $financeStats,
            'recentRegistrations' => $recentRegistrations,
            'salesRepStats'       => $salesRepStats,
            'repRanking'          => $repRanking,
            'currentYear'         => $year,
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAsesor = $user->role === 'sales' && $user->sales_type !== 'lider';

        $query = SalesRegistration::with([
            'designer:id,first_name,last_name,email,phone,status',
            'designer.designerProfile:id,user_id,brand_name',
            'event:id,name',
            'package:id,name',
            'salesRep:id,first_name,last_name',
            'documents',
        ])->whereHas('designer')
          ->when($isAsesor, fn($q) => $q->where('sales_rep_id', $user->id));

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('designer', function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhereHas('designerProfile', fn($pq) =>
                      $pq->where('brand_name', 'ilike', "%{$search}%")
                  );
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('event')) {
            $query->where('event_id', $request->event);
        }

        if ($request->filled('package')) {
            $query->where('package_id', $request->package);
        }

        if ($request->filled('sales_rep')) {
            $query->where('sales_rep_id', $request->sales_rep);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('sales_registrations.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sales_registrations.created_at', '<=', $request->date_to);
        }

        $totalCount = (clone $query)->count();

        $registrations = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $events = Event::whereIn('status', ['published', 'active', 'draft'])
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        $packages = DesignerPackage::ordered()->get(['id', 'name']);

        $isLider = $user->role === 'admin' || ($user->role === 'sales' && $user->sales_type === 'lider');
        $salesReps = $isLider
            ? User::where('role', 'sales')->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            : collect();

        return Inertia::render('Admin/Sales/Designers', [
            'registrations' => $registrations,
            'totalCount'    => $totalCount,
            'events'        => $events,
            'packages'      => $packages,
            'salesReps'     => $salesReps,
            'isLeader'      => $isLider,
            'filters'       => $request->only(['search', 'status', 'event', 'package', 'sales_rep', 'date_from', 'date_to']),
        ]);
    }

    public function exportDesigners(Request $request)
    {
        $user = $request->user();
        $isAsesor = $user->role === 'sales' && $user->sales_type !== 'lider';

        $query = SalesRegistration::with(['designer:id,first_name,last_name,email,phone', 'designer.designerProfile:id,user_id,brand_name', 'event:id,name', 'package:id,name', 'salesRep:id,first_name,last_name'])
            ->whereHas('designer')
            ->when($isAsesor, fn($q) => $q->where('sales_rep_id', $user->id));

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('designer', fn($q) => $q->where('first_name', 'ilike', "%{$search}%")->orWhere('last_name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"));
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('event')) $query->where('event_id', $request->event);
        if ($request->filled('package')) $query->where('package_id', $request->package);
        if ($request->filled('sales_rep')) $query->where('sales_rep_id', $request->sales_rep);
        if ($request->filled('date_from')) $query->whereDate('sales_registrations.created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('sales_registrations.created_at', '<=', $request->date_to);

        $rows = $query->orderByDesc('created_at')->get();

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Designer', 'Email', 'Phone', 'Brand', 'Event', 'Package', 'Price', 'Down Payment', 'Sales Rep', 'Status', 'Date']);
        foreach ($rows as $r) {
            fputcsv($csv, [
                ($r->designer?->first_name ?? '') . ' ' . ($r->designer?->last_name ?? ''),
                $r->designer?->email,
                $r->designer?->phone,
                $r->designer?->designerProfile?->brand_name,
                $r->event?->name,
                $r->package?->name,
                $r->agreed_price,
                $r->downpayment,
                ($r->salesRep?->first_name ?? '') . ' ' . ($r->salesRep?->last_name ?? ''),
                $r->status,
                $r->created_at?->format('Y-m-d'),
            ]);
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="designer-registrations-' . now()->format('Y-m-d') . '.csv"');
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $isLider = $user->role === 'admin' || $user->sales_type === 'lider';

        $events = Event::whereNotIn('status', ['draft'])
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        $packages = DesignerPackage::ordered()->get();

        $salesReps = $isLider
            ? User::where('role', 'sales')->where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            : null;

        return Inertia::render('Admin/Sales/DesignerCreate', [
            'events'    => $events,
            'packages'  => $packages,
            'countries'  => Country::active()->ordered()->get(['name', 'code', 'phone', 'flag']),
            'categories' => DesignerCategory::where('is_active', true)->ordered()->get(['id', 'name']),
            'salesReps'  => $salesReps,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone'       => ['nullable', 'string', Rule::unique('users', 'phone')->whereNull('deleted_at')],
            'brand_name'  => 'required|string|max:255',
            'country'     => 'required|string|max:255',
            'event_id'    => 'required|exists:events,id',
            'package_id'  => 'required|exists:designer_packages,id',
            'agreed_price'=> 'required|numeric|min:0',
            'downpayment' => 'required|numeric|min:0',
            'installments_count' => 'required|integer|min:1',
            'looks'           => 'nullable|integer|min:1|max:100',
            'assistants'      => 'nullable|integer|min:0|max:20',
            'model_casting_enabled' => 'boolean',
            'media_package'         => 'boolean',
            'custom_background'     => 'boolean',
            'courtesy_tickets'      => 'boolean',
            'instagram'       => 'nullable|string|max:100',
            'category_id'     => 'nullable|exists:designer_categories,id',
            'notes'           => 'nullable|string',
            'sales_rep_id'    => 'nullable|exists:users,id',
            'documents'           => 'nullable|array',
            'documents.*.file'    => 'required|file|max:10240',
            'documents.*.type'    => 'required|in:contract,payment_proof,other',
            'documents.*.notes'   => 'nullable|string|max:500',
        ], [
            'email.unique' => 'Este email ya está registrado.',
            'phone.unique' => 'Este número de teléfono ya está registrado.',
        ]);

        $currentUser = $request->user();
        $isLider = $currentUser->role === 'admin' || $currentUser->sales_type === 'lider';
        $assignedRepId = ($isLider && $request->filled('sales_rep_id'))
            ? $request->sales_rep_id
            : $currentUser->id;

        $designer = DB::transaction(function () use ($request, $assignedRepId) {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'password'   => bcrypt('runway7'),
                'role'       => 'designer',
                'status'     => 'registered',
            ]);

            $user->designerProfile()->create([
                'brand_name'   => $request->brand_name,
                'country'      => $request->country,
                'instagram'    => $request->instagram,
                'category_id'  => $request->category_id,
                'sales_rep_id' => $assignedRepId,
            ]);

            SalesRegistration::create([
                'sales_rep_id' => $assignedRepId,
                'designer_id'  => $user->id,
                'event_id'     => $request->event_id,
                'package_id'   => $request->package_id,
                'agreed_price' => $request->agreed_price ?? 0,
                'downpayment'  => $request->downpayment,
                'installments_count' => $request->installments_count,
                'notes'        => $request->notes,
                'status'       => 'registered',
            ]);

            // Asignar al evento inmediatamente (sin show, eso lo asigna operation)
            $package = DesignerPackage::find($request->package_id);
            $this->designerService->assignToEvent($user, $request->event_id, [
                'package_id'            => $request->package_id,
                'package_price'         => $request->agreed_price,
                'looks'                 => $request->filled('looks') ? (int) $request->looks : ($package?->default_looks ?? 10),
                'assistants'            => $request->filled('assistants') ? (int) $request->assistants : ($package?->default_assistants ?? 1),
                'model_casting_enabled' => $request->boolean('model_casting_enabled', true),
                'media_package'         => $request->boolean('media_package'),
                'custom_background'     => $request->boolean('custom_background'),
                'courtesy_tickets'      => $request->boolean('courtesy_tickets'),
            ]);

            // Guardar documentos si se subieron
            // Nota: hasFile() falla con arrays anidados (documents[0][file]), se lee directo
            $docsFiles = $request->file('documents', []);
            if (!empty($docsFiles)) {
                $registration = SalesRegistration::where('designer_id', $user->id)
                    ->where('event_id', $request->event_id)
                    ->first();

                $docsInput = $request->input('documents', []);
                foreach ($docsFiles as $i => $doc) {
                    if (empty($doc['file'])) continue;
                    $path = $doc['file']->store("sales/registrations/{$registration->id}", 'public');
                    SalesDocument::create([
                        'sales_registration_id' => $registration->id,
                        'uploaded_by'           => $request->user()->id,
                        'type'                  => $docsInput[$i]['type'] ?? 'other',
                        'file_path'             => $path,
                        'original_name'         => $doc['file']->getClientOriginalName(),
                        'notes'                 => $docsInput[$i]['notes'] ?? null,
                    ]);
                }
            }

            return $user;
        });

        // Correo de bienvenida al designer (from: designers@runway7fashion.com)
        $eventName = Event::find($request->event_id)?->name;
        $brandName = $designer->designerProfile?->brand_name;
        SendDesignerWelcomeSalesJob::dispatch($designer->id, $brandName, $eventName);

        // Notificar a operaciones y admin
        $notifyUsers = User::where(function ($q) use ($currentUser) {
            $q->whereIn('role', ['admin', 'operation', 'accounting'])
              ->orWhere(function ($q2) use ($currentUser) {
                  // Líderes de ventas (excepto si el creador ya es lider, para no auto-notificar)
                  $q2->where('role', 'sales')
                     ->where('sales_type', 'lider')
                     ->where('id', '!=', $currentUser->id);
              });
        })->get();

        foreach ($notifyUsers as $notifyUser) {
            $notifyUser->notify(new NewDesignerRegistered($designer, $currentUser));
        }

        // Auto-update lead statuses if converted from a lead
        if ($request->filled('lead_id')) {
            $lead = \App\Models\DesignerLead::find($request->lead_id);
            if ($lead) {
                $lead->update(['converted_designer_id' => $designer->id]);

                // Update event status to converted
                if ($request->filled('event_id')) {
                    $leadEvent = $lead->leadEvents()->where('event_id', $request->event_id)->first();
                    if ($leadEvent) {
                        $leadEvent->update(['status' => 'converted']);
                    }
                }

                // Recalculate lead status (will set to client if converted event exists)
                $lead->recalculateStatus();
            }
        }

        return redirect()->route('admin.sales.designers.index')
            ->with('success', "Designer {$designer->full_name} registered successfully.");
    }

    public function show(SalesRegistration $registration): Response
    {
        $user = request()->user();
        if ($user->role === 'sales' && $user->sales_type !== 'lider' && $registration->sales_rep_id !== $user->id) {
            abort(403);
        }

        $registration->load([
            'designer:id,first_name,last_name,email,phone,status,profile_picture,created_at',
            'designer.designerProfile:id,user_id,brand_name,country,website,instagram',
            'event:id,name,status,start_date,end_date',
            'package:id,name,price',
            'salesRep:id,first_name,last_name',
            'onboardedBy:id,first_name,last_name',
            'confirmedBy:id,first_name,last_name',
            'documents.uploader:id,first_name,last_name',
        ]);

        $salesReps = null;
        if ($user->role === 'admin' || $user->sales_type === 'lider') {
            $salesReps = User::where('role', 'sales')->where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        }

        // Check if undo conversion is safe
        $canUndo = false;
        $undoBlockReason = null;
        $isLider = $user->role === 'admin' || $user->sales_type === 'lider';

        if ($isLider && $registration->designer) {
            $designerId = $registration->designer->id;
            $reasons = [];

            // Check if operations touched the designer
            if ($registration->status !== 'registered') {
                $reasons[] = 'Registration has been processed (status: ' . $registration->status . ')';
            }
            if ($registration->designer->status !== 'registered') {
                $reasons[] = 'Designer status has changed (status: ' . $registration->designer->status . ')';
            }
            if ($registration->designer->eventsAsDesigner()->exists()) {
                $reasons[] = 'Designer has events assigned by Operations';
            }
            if ($registration->designer->designedShows()->exists()) {
                $reasons[] = 'Designer has shows assigned';
            }

            // Check if accounting touched the designer
            if (\App\Models\DesignerPaymentPlan::where('designer_id', $designerId)->exists()) {
                $reasons[] = 'Accounting has created a payment plan';
            }

            if (empty($reasons)) {
                $canUndo = true;
            } else {
                $undoBlockReason = $reasons;
            }
        }

        return Inertia::render('Admin/Sales/DesignerShow', [
            'registration'    => $registration,
            'salesReps'       => $salesReps,
            'canUndo'         => $canUndo,
            'undoBlockReason' => $undoBlockReason,
        ]);
    }

    public function update(Request $request, SalesRegistration $registration)
    {
        $user = $request->user();
        $isLider = $user->role === 'admin' || $user->sales_type === 'lider';

        if (!$isLider) {
            abort(403);
        }

        $request->validate([
            'sales_rep_id' => 'nullable|exists:users,id',
            'notes'        => 'nullable|string',
        ]);

        $registration->update([
            'sales_rep_id' => $request->sales_rep_id,
            'notes'        => $request->notes,
        ]);

        return back()->with('success', 'Registration updated.');
    }

    public function undoConversion(SalesRegistration $registration)
    {
        $user = request()->user();
        $isLider = $user->role === 'admin' || ($user->role === 'sales' && $user->sales_type === 'lider');
        if (!$isLider) abort(403);

        $designer = $registration->designer;
        if (!$designer) {
            return back()->withErrors(['error' => 'Designer not found for this registration.']);
        }

        // Safety validations
        if ($registration->status !== 'registered') {
            return back()->withErrors(['error' => 'Cannot undo: registration has already been processed by Operations (status: ' . $registration->status . ').']);
        }
        if ($designer->status !== 'registered') {
            return back()->withErrors(['error' => 'Cannot undo: designer status has changed (status: ' . $designer->status . ').']);
        }
        if ($designer->eventsAsDesigner()->exists()) {
            return back()->withErrors(['error' => 'Cannot undo: Operations has assigned events to this designer.']);
        }
        if ($designer->designedShows()->exists()) {
            return back()->withErrors(['error' => 'Cannot undo: designer has shows assigned.']);
        }
        if (\App\Models\DesignerPaymentPlan::where('designer_id', $designer->id)->exists()) {
            return back()->withErrors(['error' => 'Cannot undo: Accounting has created a payment plan.']);
        }

        // Revert the lead
        $lead = DesignerLead::where('converted_designer_id', $designer->id)->first();
        if ($lead) {
            $lead->update(['converted_designer_id' => null]);

            // Revert lead_event status from converted → negotiating
            $lead->leadEvents()
                ->where('status', 'converted')
                ->update(['status' => 'negotiating']);

            $lead->recalculateStatus();
        }

        // Delete registration documents from disk
        foreach ($registration->documents as $doc) {
            \Storage::disk('public')->delete($doc->file_path);
            $doc->delete();
        }

        // Delete registration
        $registration->delete();

        // Delete designer profile and user
        if ($designer->designerProfile) {
            $designer->designerProfile->delete();
        }
        $designer->forceDelete();

        return redirect()->route('admin.sales.designers.index')
            ->with('success', 'Conversion undone successfully. The lead has been reverted to its previous state.');
    }

    public function uploadDocument(Request $request, SalesRegistration $registration)
    {
        $user = $request->user();
        if ($user->role === 'sales' && $user->sales_type !== 'lider' && $registration->sales_rep_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'file'  => 'required|file|max:10240',
            'type'  => 'required|in:contract,payment_proof,other',
            'notes' => 'nullable|string|max:500',
        ]);

        $path = $request->file('file')->store(
            "sales/registrations/{$registration->id}",
            'public'
        );

        SalesDocument::create([
            'sales_registration_id' => $registration->id,
            'uploaded_by'           => $user->id,
            'type'                  => $request->type,
            'file_path'             => $path,
            'original_name'         => $request->file('file')->getClientOriginalName(),
            'notes'                 => $request->notes,
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function deleteDocument(SalesDocument $document)
    {
        $user = request()->user();
        $registration = $document->registration;

        if ($user->role === 'sales' && $user->sales_type !== 'lider' && $registration->sales_rep_id !== $user->id) {
            abort(403);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    public function history(Request $request): Response
    {
        $user = $request->user();
        $isLider = $user->role === 'admin' || ($user->role === 'sales' && $user->sales_type === 'lider');
        if (!$isLider) abort(403);

        $year       = (int) ($request->year    ?? now()->year);
        $eventId    = $request->filled('event')    ? (int) $request->event    : null;
        $repId      = $request->filled('rep')      ? (int) $request->rep      : null;

        // Base query with filters
        $base = fn() => SalesRegistration::query()
            ->whereHas('designer')
            ->whereYear('sales_registrations.created_at', $year)
            ->when($eventId, fn($q) => $q->where('event_id', $eventId))
            ->when($repId,   fn($q) => $q->where('sales_rep_id', $repId));

        // ── KPI cards ──────────────────────────────────────────────────
        $total      = $base()->count();
        $confirmed  = $base()->where('status', 'confirmed')->count();
        $cancelled  = $base()->where('status', 'cancelled')->count();
        $revenue    = (float) $base()->where('status', '!=', 'cancelled')->sum('agreed_price');
        $downpay    = (float) $base()->where('status', '!=', 'cancelled')->sum('downpayment');
        $avgDeal    = ($total - $cancelled) > 0 ? round($revenue / ($total - $cancelled), 2) : 0;
        // Conversion rate = designers registered / total leads
        $totalLeadsHistory = DesignerLead::whereNull('deleted_at')
            ->when($repId, fn($q) => $q->where('assigned_to', $repId))
            ->count();
        $confRate = $totalLeadsHistory > 0 ? round(($total / $totalLeadsHistory) * 100) : 0;

        // Best month (most registrations)
        $byMonth = $base()
            ->selectRaw('EXTRACT(MONTH FROM created_at)::int as month, COUNT(*) as cnt')
            ->groupBy('month')
            ->orderByDesc('cnt')
            ->first();
        $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $bestMonth  = $byMonth ? $monthNames[$byMonth->month - 1] : '—';

        // ── Monthly series (12 months) ──────────────────────────────────
        $monthlyRegs = array_fill(0, 12, 0);
        $monthlyRev  = array_fill(0, 12, 0.0);

        $regsByMonth = $base()
            ->selectRaw('EXTRACT(MONTH FROM created_at)::int as month, COUNT(*) as cnt')
            ->groupBy('month')
            ->get()->keyBy('month');

        $revByMonth = $base()
            ->where('status', '!=', 'cancelled')
            ->selectRaw('EXTRACT(MONTH FROM created_at)::int as month, SUM(agreed_price) as total')
            ->groupBy('month')
            ->get()->keyBy('month');

        for ($m = 1; $m <= 12; $m++) {
            $monthlyRegs[$m - 1] = (int)   ($regsByMonth[$m]->cnt   ?? 0);
            $monthlyRev[$m - 1]  = (float) ($revByMonth[$m]->total  ?? 0);
        }

        // ── Sales rep ranking ────────────────────────────────────────────
        $reps = User::where('role', 'sales')->where('sales_type', 'asesor')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'sales_type']);

        $repRanking = $reps->map(function ($rep) use ($base, $year, $eventId) {
            $q = fn() => SalesRegistration::whereHas('designer')
                ->whereYear('sales_registrations.created_at', $year)
                ->when($eventId, fn($q) => $q->where('event_id', $eventId))
                ->where('sales_rep_id', $rep->id);

            $total     = $q()->count();
            $confirmed = $q()->where('status', 'confirmed')->count();
            $cancelled = $q()->where('status', 'cancelled')->count();
            $revenue   = (float) $q()->where('status', '!=', 'cancelled')->sum('agreed_price');

            // Conversion rate = designers registered / total leads assigned
            $totalLeads = \App\Models\DesignerLead::where('assigned_to', $rep->id)->whereNull('deleted_at')->count();

            return [
                'id'               => $rep->id,
                'name'             => $rep->full_name,
                'sales_type'       => $rep->sales_type,
                'total'            => $total,
                'confirmed'        => $confirmed,
                'cancelled'        => $cancelled,
                'revenue'          => $revenue,
                'total_leads'      => $totalLeads,
                'conversion_rate'  => $totalLeads > 0 ? round(($total / $totalLeads) * 100) : 0,
            ];
        })
        ->filter(fn($r) => $r['total'] > 0 || $r['total_leads'] > 0)
        ->sortByDesc('total')
        ->values();

        // ── Status distribution ──────────────────────────────────────────
        $statusDist = [
            'registered' => $base()->where('status', 'registered')->count(),
            'onboarded'  => $base()->where('status', 'onboarded')->count(),
            'confirmed'  => $confirmed,
            'cancelled'  => $cancelled,
        ];

        // ── Packages breakdown ───────────────────────────────────────────
        $packageBreakdown = $base()
            ->join('designer_packages', 'designer_packages.id', '=', 'sales_registrations.package_id')
            ->selectRaw('designer_packages.name as package_name, COUNT(*) as cnt, SUM(sales_registrations.agreed_price) as revenue')
            ->groupBy('designer_packages.name')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn($r) => [
                'name'    => $r->package_name,
                'count'   => (int) $r->cnt,
                'revenue' => (float) $r->revenue,
            ])
            ->values();

        // ── Table (paginated) ────────────────────────────────────────────
        $tableQuery = SalesRegistration::with([
            'designer:id,first_name,last_name,email',
            'designer.designerProfile:id,user_id,brand_name',
            'event:id,name',
            'package:id,name',
            'salesRep:id,first_name,last_name',
        ])
        ->whereHas('designer')
        ->whereYear('sales_registrations.created_at', $year)
        ->when($eventId, fn($q) => $q->where('event_id', $eventId))
        ->when($repId,   fn($q) => $q->where('sales_rep_id', $repId));

        if ($request->filled('search')) {
            $s = $request->search;
            $tableQuery->whereHas('designer', fn($q) =>
                $q->where('first_name', 'ilike', "%{$s}%")
                  ->orWhere('last_name',  'ilike', "%{$s}%")
                  ->orWhereHas('designerProfile', fn($p) => $p->where('brand_name', 'ilike', "%{$s}%"))
            );
        }

        $table = $tableQuery->orderBy('sales_registrations.created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // ── Filter options ───────────────────────────────────────────────
        $availableYears = SalesRegistration::selectRaw('EXTRACT(YEAR FROM created_at)::int as year')
            ->groupBy('year')->orderByDesc('year')->pluck('year');

        $availableEvents = Event::whereIn('id',
            SalesRegistration::whereYear('created_at', $year)->select('event_id')->distinct()
        )->orderBy('start_date', 'desc')->get(['id', 'name']);

        $availableReps = User::where('role', 'sales')
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return Inertia::render('Admin/Sales/History', [
            'kpis' => [
                'total'             => $total,
                'revenue'           => $revenue,
                'downpayments'      => $downpay,
                'avg_deal'          => $avgDeal,
                'conversion_rate'   => $confRate,
                'best_month'        => $bestMonth,
            ],
            'monthly_regs'       => $monthlyRegs,
            'monthly_revenue'    => $monthlyRev,
            'rep_ranking'        => $repRanking,
            'status_dist'        => $statusDist,
            'package_breakdown'  => $packageBreakdown,
            'table'              => $table,
            'filters' => [
                'year'   => $year,
                'event'  => $eventId,
                'rep'    => $repId,
                'search' => $request->search,
            ],
            'available_years'  => $availableYears,
            'available_events' => $availableEvents,
            'available_reps'   => $availableReps,
        ]);
    }

    public function historyExport(Request $request)
    {
        $user = $request->user();
        $isLider = $user->role === 'admin' || ($user->role === 'sales' && $user->sales_type === 'lider');
        if (!$isLider) abort(403);

        $year    = (int) ($request->year ?? now()->year);
        $eventId = $request->filled('event') ? (int) $request->event : null;
        $repId   = $request->filled('rep')   ? (int) $request->rep   : null;

        $rows = SalesRegistration::with([
            'designer:id,first_name,last_name,email',
            'designer.designerProfile:id,user_id,brand_name',
            'event:id,name',
            'package:id,name',
            'salesRep:id,first_name,last_name',
        ])
        ->whereHas('designer')
        ->whereYear('sales_registrations.created_at', $year)
        ->when($eventId, fn($q) => $q->where('event_id', $eventId))
        ->when($repId,   fn($q) => $q->where('sales_rep_id', $repId))
        ->orderBy('created_at', 'desc')
        ->get();

        $csv = "Date,Designer,Brand,Email,Event,Package,Sales Rep,Status,Agreed Price,Downpayment\n";
        foreach ($rows as $r) {
            $csv .= implode(',', [
                $r->created_at->format('Y-m-d'),
                "\"{$r->designer?->full_name}\"",
                "\"{$r->designer?->designerProfile?->brand_name}\"",
                $r->designer?->email,
                "\"{$r->event?->name}\"",
                "\"{$r->package?->name}\"",
                "\"{$r->salesRep?->full_name}\"",
                $r->status,
                $r->agreed_price,
                $r->downpayment,
            ]) . "\n";
        }

        $filename = "sales-history-{$year}.csv";
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

}
