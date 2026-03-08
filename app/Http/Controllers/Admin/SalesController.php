<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignerPackage;
use App\Models\Event;
use App\Models\SalesDocument;
use App\Models\SalesRegistration;
use App\Models\User;
use App\Notifications\NewDesignerRegistered;
use App\Services\DesignerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function __construct(protected DesignerService $designerService) {}

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $isSales = $user->role === 'sales';

        $baseQuery = fn() => SalesRegistration::query()
            ->when($isSales, fn($q) => $q->where('sales_rep_id', $user->id));

        $stats = [
            'total_registrations' => $baseQuery()->count(),
            'registered'          => $baseQuery()->where('status', 'registered')->count(),
            'onboarded'           => $baseQuery()->where('status', 'onboarded')->count(),
            'confirmed'           => $baseQuery()->where('status', 'confirmed')->count(),
            'cancelled'           => $baseQuery()->where('status', 'cancelled')->count(),
            'active_events'       => Event::where('status', 'active')->count(),
        ];

        $recentRegistrations = $baseQuery()
            ->with(['designer:id,first_name,last_name,email', 'event:id,name', 'package:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Sales/Dashboard', [
            'stats'               => $stats,
            'recentRegistrations' => $recentRegistrations,
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSales = $user->role === 'sales';

        $query = SalesRegistration::with([
            'designer:id,first_name,last_name,email,phone,status',
            'designer.designerProfile:id,user_id,brand_name',
            'event:id,name',
            'package:id,name',
            'salesRep:id,first_name,last_name',
            'documents',
        ])->when($isSales, fn($q) => $q->where('sales_rep_id', $user->id));

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

        $registrations = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $events = Event::whereIn('status', ['published', 'active', 'draft'])
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Sales/Designers', [
            'registrations' => $registrations,
            'events'        => $events,
            'filters'       => $request->only(['search', 'status', 'event']),
        ]);
    }

    public function create(): Response
    {
        $events = Event::whereNotIn('status', ['draft'])
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        $packages = DesignerPackage::ordered()->get();

        return Inertia::render('Admin/Sales/DesignerCreate', [
            'events'    => $events,
            'packages'  => $packages,
            'countries' => config('countries'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => 'required|email|unique:users',
            'phone'       => 'nullable|string|unique:users,phone',
            'brand_name'  => 'required|string|max:255',
            'country'     => 'required|string|max:255',
            'event_id'    => 'required|exists:events,id',
            'package_id'  => 'required|exists:designer_packages,id',
            'agreed_price'=> 'required|numeric|min:0',
            'downpayment' => 'required|numeric|min:0',
            'notes'       => 'nullable|string',
        ], [
            'email.unique' => 'Este email ya está registrado.',
            'phone.unique' => 'Este número de teléfono ya está registrado.',
        ]);

        $designer = DB::transaction(function () use ($request) {
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
                'brand_name' => $request->brand_name,
                'country'    => $request->country,
                'sales_rep_id' => $request->user()->id,
            ]);

            SalesRegistration::create([
                'sales_rep_id' => $request->user()->id,
                'designer_id'  => $user->id,
                'event_id'     => $request->event_id,
                'package_id'   => $request->package_id,
                'agreed_price' => $request->agreed_price ?? 0,
                'downpayment'  => $request->downpayment,
                'notes'        => $request->notes,
                'status'       => 'registered',
            ]);

            return $user;
        });

        // Notificar a operaciones y admin
        $notifyUsers = User::whereIn('role', ['admin', 'operation', 'accounting'])->get();
        foreach ($notifyUsers as $notifyUser) {
            $notifyUser->notify(new NewDesignerRegistered($designer, $request->user()));
        }

        return redirect()->route('admin.sales.designers.index')
            ->with('success', "Diseñador {$designer->full_name} registrado exitosamente.");
    }

    public function show(SalesRegistration $registration): Response
    {
        $user = request()->user();
        if ($user->role === 'sales' && $registration->sales_rep_id !== $user->id) {
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

        return Inertia::render('Admin/Sales/DesignerShow', [
            'registration' => $registration,
        ]);
    }

    public function uploadDocument(Request $request, SalesRegistration $registration)
    {
        $user = $request->user();
        if ($user->role === 'sales' && $registration->sales_rep_id !== $user->id) {
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

        return back()->with('success', 'Documento subido exitosamente.');
    }

    public function deleteDocument(SalesDocument $document)
    {
        $user = request()->user();
        $registration = $document->registration;

        if ($user->role === 'sales' && $registration->sales_rep_id !== $user->id) {
            abort(403);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Documento eliminado.');
    }

    public function updateStatus(Request $request, SalesRegistration $registration)
    {
        $request->validate([
            'status' => 'required|in:registered,onboarded,confirmed,cancelled',
        ]);

        $newStatus = $request->status;
        $user = $request->user();

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'onboarded') {
            $updateData['onboarded_at'] = now();
            $updateData['onboarded_by'] = $user->id;
        }

        if ($newStatus === 'confirmed') {
            $updateData['confirmed_at'] = now();
            $updateData['confirmed_by'] = $user->id;

            // Al confirmar, asignar el diseñador al evento y cambiar su status a active
            $this->confirmRegistration($registration);
        }

        $registration->update($updateData);

        return back()->with('success', 'Estado actualizado.');
    }

    private function confirmRegistration(SalesRegistration $registration): void
    {
        $designer = User::findOrFail($registration->designer_id);

        // Cambiar status del diseñador a active
        $designer->update(['status' => 'active']);

        // Asignar al evento si no está ya asignado
        $event = Event::findOrFail($registration->event_id);
        if (!$event->designers()->where('designer_id', $designer->id)->exists()) {
            $this->designerService->assignToEvent($designer, $registration->event_id, [
                'package_id'    => $registration->package_id,
                'package_price' => $registration->agreed_price,
                'looks'         => 10,
            ]);
        }
    }
}
