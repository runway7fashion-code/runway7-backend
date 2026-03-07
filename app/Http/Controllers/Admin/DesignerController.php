<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignerAssistant;
use App\Models\DesignerCategory;
use App\Models\DesignerDisplay;
use App\Models\DesignerMaterial;
use App\Models\DesignerPackage;
use App\Models\Event;
use App\Models\FittingSlot;
use App\Models\Show;
use App\Models\User;
use App\Exports\DesignersExport;
use App\Imports\DesignersImport;
use App\Services\DesignerService;
use App\Services\EventService;
use App\Services\TwilioService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DesignerController extends Controller
{
    public function __construct(
        protected DesignerService $designerService,
        protected EventService $eventService,
        protected TwilioService $twilioService,
    ) {}

    public function index(Request $request): Response
    {
        $query = User::designers()->with([
            'designerProfile.category',
            'eventsAsDesigner',
            'designerMaterials',
            'eventPasses' => fn($q) => $q->whereNotNull('checked_in_at')->with('event:id,name,status')->select('id', 'event_id', 'user_id', 'checked_in_at', 'check_in_history'),
        ]);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'ilike', "%{$request->search}%")
                  ->orWhere('last_name', 'ilike', "%{$request->search}%")
                  ->orWhere('email', 'ilike', "%{$request->search}%");
            });
        }

        if ($request->filled('event')) {
            $query->whereHas('eventsAsDesigner', fn($q) => $q->where('events.id', $request->event));
        }

        if ($request->filled('category')) {
            $query->whereHas('designerProfile', fn($q) => $q->where('category_id', $request->category));
        }

        if ($request->filled('package')) {
            $query->whereHas('eventsAsDesigner', fn($q) => $q->where('event_designer.package_id', $request->package));
        }

        if ($request->filled('sales_rep')) {
            $query->whereHas('designerProfile', fn($q) => $q->where('sales_rep_id', $request->sales_rep));
        }

        if ($request->filled('country')) {
            $query->whereHas('designerProfile', fn($q) => $q->where('country', $request->country));
        }

        if ($request->filled('materials')) {
            if ($request->materials === 'complete') {
                $query->whereHas('designerMaterials')
                      ->whereDoesntHave('designerMaterials', fn($q) => $q->whereIn('status', ['pending', 'rejected']));
            } elseif ($request->materials === 'incomplete') {
                $query->whereHas('designerMaterials', fn($q) => $q->whereIn('status', ['pending', 'rejected']));
            }
        }

        $designers = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $events = Event::orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        $categories = DesignerCategory::ordered()->get();
        $packages = DesignerPackage::ordered()->get();
        $salesReps = User::where('role', 'sales')->active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        $usedCountries = \App\Models\DesignerProfile::whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        $pendingEmailCount = User::designers()
            ->where('status', 'pending')
            ->whereNull('welcome_email_sent_at')
            ->count();

        $pendingSmsCount = User::designers()
            ->where('status', 'pending')
            ->whereNotNull('phone')
            ->where('phone', 'like', '+%')
            ->whereNull('sms_sent_at')
            ->count();

        return Inertia::render('Admin/Designers/Index', [
            'designers'         => $designers,
            'events'            => $events,
            'categories'        => $categories,
            'packages'          => $packages,
            'salesReps'         => $salesReps,
            'countries'         => $usedCountries,
            'pendingEmailCount' => $pendingEmailCount,
            'pendingSmsCount'   => $pendingSmsCount,
            'twilioBalance'     => $this->twilioService->getBalance(),
            'filters'           => $request->only(['search', 'event', 'category', 'package', 'sales_rep', 'materials', 'country']),
        ]);
    }

    public function create(): Response
    {
        $events = $this->getEventsWithShows();
        $categories = DesignerCategory::ordered()->get();
        $packages = DesignerPackage::ordered()->get();
        $salesReps = User::where('role', 'sales')->active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return Inertia::render('Admin/Designers/Create', [
            'events'     => $events,
            'categories' => $categories,
            'packages'   => $packages,
            'countries'  => config('countries'),
            'salesReps'  => $salesReps,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => 'required|email|unique:users',
            'phone'           => 'nullable|string',
            'brand_name'      => 'required|string|max:255',
            'collection_name' => 'nullable|string|max:255',
            'website'         => 'nullable|string|max:255',
            'instagram'       => 'nullable|string|max:255',
            'bio'             => 'nullable|string',
            'country'         => 'nullable|string|max:255',
            'category_id'     => 'nullable|exists:designer_categories,id',
            'sales_rep_id'    => 'nullable|exists:users,id',
            'tracking_link'   => 'nullable|string|max:255',
            'skype'           => 'nullable|string|max:255',
            'social_media'    => 'nullable|array',
            'event_id'        => 'nullable|exists:events,id',
            'package_id'      => 'nullable|exists:designer_packages,id',
            'looks'           => 'nullable|integer|min:0',
            'model_casting_enabled' => 'boolean',
            'package_price'   => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'assistants'              => 'nullable|array',
            'assistants.*.full_name'  => 'required|string|max:255',
            'assistants.*.document_id'=> 'nullable|string|max:255',
            'assistants.*.phone'      => 'nullable|string|max:255',
            'assistants.*.email'      => 'nullable|email|max:255',
            'shows'                   => 'nullable|array',
            'shows.*.show_id'         => 'required|exists:shows,id',
            'shows.*.collection_name' => 'nullable|string|max:255',
            'fitting_slot_id'         => 'nullable|exists:fitting_slots,id',
        ]);

        $userData = $request->only(['first_name', 'last_name', 'email', 'phone']);
        $profileData = $request->only([
            'brand_name', 'collection_name', 'website', 'instagram',
            'bio', 'country', 'category_id', 'sales_rep_id', 'tracking_link', 'skype', 'social_media',
        ]);

        $eventData = $request->only(['package_id', 'looks', 'model_casting_enabled', 'package_price', 'notes']);

        $designer = $this->designerService->createDesigner(
            $userData,
            $profileData,
            $request->event_id,
            $eventData,
        );

        if ($request->filled('assistants')) {
            foreach ($request->assistants as $assistantData) {
                $this->designerService->addAssistant($designer, $request->event_id, $assistantData, $request->user()->id);
            }
        }

        if ($request->filled('shows')) {
            foreach ($request->shows as $showData) {
                $designer->designedShows()->attach($showData['show_id'], [
                    'collection_name' => $showData['collection_name'] ?? null,
                    'status'          => 'confirmed',
                ]);
            }
        }

        // Asignar fitting si se seleccionó un slot
        if ($request->filled('fitting_slot_id')) {
            $fittingSlot = FittingSlot::find($request->fitting_slot_id);
            if ($fittingSlot) {
                try {
                    $this->eventService->assignDesignerToFitting($fittingSlot, $designer->id);
                } catch (\Exception $e) {
                    // Ya está asignado, ignorar
                }
            }
        }

        if ($request->filled('event_id')) {
            $this->designerService->syncDesignerPass($designer, $request->event_id, $request->user()->id);
        }

        return redirect()->route('admin.designers.show', $designer)
            ->with('success', 'Diseñador creado exitosamente.');
    }

    public function show(User $designer): Response
    {
        $this->authorizeDesigner($designer);

        $designer->load([
            'designerProfile.category',
            'designerProfile.salesRep',
            'eventsAsDesigner.eventDays',
            'designedShows.eventDay',
            'designerAssistants',
            'designerMaterials',
            'designerDisplays',
            'fittingAssignments.fittingSlot.eventDay',
            'eventPasses',
        ]);

        return Inertia::render('Admin/Designers/Show', [
            'designer' => $this->formatDesignerForView($designer),
        ]);
    }

    public function edit(User $designer): Response
    {
        $this->authorizeDesigner($designer);

        $designer->load([
            'designerProfile.category',
            'designerProfile.salesRep',
            'eventsAsDesigner',
            'designedShows.eventDay',
            'designerAssistants',
            'designerMaterials',
            'designerDisplays',
            'fittingAssignments.fittingSlot.eventDay',
        ]);

        $events = $this->getEventsWithShows();
        $categories = DesignerCategory::ordered()->get();
        $packages = DesignerPackage::ordered()->get();
        $salesReps = User::where('role', 'sales')->active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return Inertia::render('Admin/Designers/Edit', [
            'designer'   => $this->formatDesignerForView($designer),
            'events'     => $events,
            'categories' => $categories,
            'packages'   => $packages,
            'salesReps'  => $salesReps,
            'countries'  => config('countries'),
        ]);
    }

    public function updateStatus(Request $request, User $designer)
    {
        $this->authorizeDesigner($designer);

        $request->validate(['status' => 'required|in:inactive,pending']);

        $designer->update(['status' => $request->status]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function update(Request $request, User $designer)
    {
        $this->authorizeDesigner($designer);

        $request->validate([
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => "required|email|unique:users,email,{$designer->id}",
            'phone'           => 'nullable|string',
            'status'          => 'nullable|in:active,inactive,pending',
            'brand_name'      => 'required|string|max:255',
            'collection_name' => 'nullable|string|max:255',
            'website'         => 'nullable|string|max:255',
            'instagram'       => 'nullable|string|max:255',
            'bio'             => 'nullable|string',
            'country'         => 'nullable|string|max:255',
            'category_id'     => 'nullable|exists:designer_categories,id',
            'sales_rep_id'    => 'nullable|exists:users,id',
            'tracking_link'   => 'nullable|string|max:255',
            'skype'           => 'nullable|string|max:255',
            'social_media'    => 'nullable|array',
        ]);

        $userData = $request->only(['first_name', 'last_name', 'email', 'phone', 'status']);
        $profileData = $request->only([
            'brand_name', 'collection_name', 'website', 'instagram',
            'bio', 'country', 'category_id', 'sales_rep_id', 'tracking_link', 'skype', 'social_media',
        ]);

        $this->designerService->updateDesigner($designer, $userData, $profileData);

        return redirect()->route('admin.designers.show', $designer)
            ->with('success', 'Diseñador actualizado exitosamente.');
    }

    public function destroy(User $designer)
    {
        $this->authorizeDesigner($designer);
        $designer->delete();

        return redirect()->route('admin.designers.index')
            ->with('success', 'Diseñador eliminado.');
    }

    public function exportDesigners(Request $request)
    {
        $filename = 'disenadores_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new DesignersExport(
            search:    $request->input('search'),
            event:     $request->input('event'),
            category:  $request->input('category'),
            package:   $request->input('package'),
            salesRep:  $request->input('sales_rep'),
            materials: $request->input('materials'),
            country:   $request->input('country'),
        ), $filename);
    }

    public function importDesigners(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'event_id' => 'nullable|exists:events,id',
        ]);

        $eventId = $request->filled('event_id') ? (int) $request->event_id : null;
        $import  = new DesignersImport(globalEventId: $eventId);
        Excel::import($import, $request->file('file'));

        $s = $import->summary;
        $msg = "Importación completada: {$s['created']} creados, {$s['updated']} actualizados, {$s['assigned']} asignados a eventos.";

        if (!empty($s['errors'])) {
            $msg .= ' ' . count($s['errors']) . ' errores (ver log).';
            \Illuminate\Support\Facades\Log::warning('DesignersImport errors', $s['errors']);
        }

        return back()->with('success', $msg)->with('importSummary', $s);
    }

    public function assignEvent(Request $request, User $designer)
    {
        $this->authorizeDesigner($designer);

        $request->validate([
            'event_id'              => 'required|exists:events,id',
            'package_id'            => 'nullable|exists:designer_packages,id',
            'looks'                 => 'nullable|integer|min:0',
            'model_casting_enabled' => 'boolean',
            'package_price'         => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string',
            'shows'                        => 'nullable|array',
            'shows.*.show_id'              => 'required|exists:shows,id',
            'shows.*.collection_name'      => 'nullable|string|max:255',
            'fitting_slot_id'       => 'nullable|exists:fitting_slots,id',
        ]);

        try {
            $this->designerService->assignToEvent(
                $designer,
                $request->event_id,
                $request->only(['package_id', 'looks', 'model_casting_enabled', 'package_price', 'notes']),
            );

            if ($request->filled('shows')) {
                foreach ($request->shows as $showData) {
                    $designer->designedShows()->attach($showData['show_id'], [
                        'collection_name' => $showData['collection_name'] ?? null,
                        'status'          => 'confirmed',
                    ]);
                }
            }

            // Asignar fitting si se seleccionó un slot
            if ($request->filled('fitting_slot_id')) {
                $fittingSlot = FittingSlot::find($request->fitting_slot_id);
                if ($fittingSlot) {
                    try {
                        $this->eventService->assignDesignerToFitting($fittingSlot, $designer->id);
                    } catch (\Exception $e) {
                        // Ya está asignado, ignorar
                    }
                }
            }

            $this->designerService->syncDesignerPass($designer, $request->event_id, $request->user()->id);

            return back()->with('success', 'Diseñador asignado al evento.');
        } catch (\Exception $e) {
            return back()->withErrors(['event' => $e->getMessage()]);
        }
    }

    public function cancelEvent(User $designer, Event $event)
    {
        $this->authorizeDesigner($designer);

        try {
            $this->designerService->cancelEventParticipation($designer, $event->id);
            return back()->with('success', 'Participación en el evento cancelada.');
        } catch (\Exception $e) {
            return back()->withErrors(['event' => $e->getMessage()]);
        }
    }

    public function removeEvent(User $designer, Event $event)
    {
        $this->authorizeDesigner($designer);

        try {
            $this->designerService->removeFromEvent($designer, $event->id);
            return back()->with('success', 'Diseñador removido del evento.');
        } catch (\Exception $e) {
            return back()->withErrors(['event' => $e->getMessage()]);
        }
    }

    public function cancelShow(User $designer, Show $show)
    {
        $this->authorizeDesigner($designer);

        $show->loadMissing('eventDay');
        $eventId = $show->eventDay->event_id;

        $designer->designedShows()->updateExistingPivot($show->id, ['status' => 'cancelled']);

        $this->designerService->syncDesignerPass($designer, $eventId, request()->user()->id);

        return back()->with('success', 'Show cancelado.');
    }

    public function removeShow(User $designer, Show $show)
    {
        $this->authorizeDesigner($designer);

        $show->loadMissing('eventDay');
        $eventId = $show->eventDay->event_id;

        $designer->designedShows()->detach($show->id);

        $this->designerService->syncDesignerPass($designer, $eventId, request()->user()->id);

        return back()->with('success', 'Show desasignado.');
    }

    public function addShow(Request $request, User $designer)
    {
        $this->authorizeDesigner($designer);

        $request->validate([
            'show_id'         => 'required|exists:shows,id',
            'collection_name' => 'nullable|string|max:255',
        ]);

        if ($designer->designedShows()->where('show_id', $request->show_id)->exists()) {
            return back()->withErrors(['show' => 'El diseñador ya está asignado a este show.']);
        }

        $designer->designedShows()->attach($request->show_id, [
            'collection_name' => $request->collection_name,
            'status'          => 'confirmed',
        ]);

        $show = Show::with('eventDay')->findOrFail($request->show_id);
        $this->designerService->syncDesignerPass($designer, $show->eventDay->event_id, $request->user()->id);

        return back()->with('success', 'Show asignado.');
    }

    public function updateFitting(Request $request, User $designer)
    {
        $this->authorizeDesigner($designer);

        $request->validate([
            'fitting_slot_id' => 'nullable|exists:fitting_slots,id',
            'event_id'        => 'required|exists:events,id',
        ]);

        // Remover fitting existente del diseñador en este evento
        $existingAssignments = $designer->fittingAssignments()
            ->whereHas('fittingSlot.eventDay', fn($q) => $q->where('event_id', $request->event_id))
            ->get();

        foreach ($existingAssignments as $assignment) {
            $assignment->delete();
        }

        // Asignar nuevo fitting si se seleccionó
        if ($request->filled('fitting_slot_id')) {
            $fittingSlot = FittingSlot::find($request->fitting_slot_id);
            if ($fittingSlot) {
                $this->eventService->assignDesignerToFitting($fittingSlot, $designer->id);
            }
        }

        return back()->with('success', 'Fitting actualizado.');
    }

    public function addAssistant(Request $request, User $designer)
    {
        $this->authorizeDesigner($designer);

        $request->validate([
            'event_id'    => 'required|exists:events,id',
            'full_name'   => 'required|string|max:255',
            'document_id' => 'nullable|string|max:255',
            'phone'       => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
        ]);

        try {
            $this->designerService->addAssistant($designer, $request->event_id, $request->only([
                'full_name', 'document_id', 'phone', 'email',
            ]), $request->user()->id);
            return back()->with('success', 'Asistente agregado.');
        } catch (\Exception $e) {
            return back()->withErrors(['assistant' => $e->getMessage()]);
        }
    }

    public function removeAssistant(DesignerAssistant $assistant)
    {
        try {
            $this->designerService->removeAssistant($assistant);
            return back()->with('success', 'Asistente eliminado.');
        } catch (\Exception $e) {
            return back()->withErrors(['assistant' => $e->getMessage()]);
        }
    }

    public function updateMaterial(Request $request, DesignerMaterial $material)
    {
        $request->validate([
            'drive_link' => 'nullable|string|max:2048',
            'status'     => 'nullable|in:pending,submitted,confirmed,rejected',
        ]);

        try {
            $this->designerService->updateMaterial($material, $request->only(['drive_link', 'status']));
            return back()->with('success', 'Material actualizado.');
        } catch (\Exception $e) {
            return back()->withErrors(['material' => $e->getMessage()]);
        }
    }

    public function updateDisplay(Request $request, DesignerDisplay $display)
    {
        $request->validate([
            'background_video_url' => 'nullable|string|max:2048',
            'music_audio_url'      => 'nullable|string|max:2048',
            'status'               => 'nullable|in:pending,ready,confirmed',
            'notes'                => 'nullable|string',
        ]);

        try {
            $this->designerService->updateDisplay($display, $request->only([
                'background_video_url', 'music_audio_url', 'status', 'notes',
            ]));
            return back()->with('success', 'Display actualizado.');
        } catch (\Exception $e) {
            return back()->withErrors(['display' => $e->getMessage()]);
        }
    }

    public function uploadVideo(Request $request, DesignerDisplay $display)
    {
        $request->validate([
            'file' => 'required|mimetypes:video/*|max:102400',
        ]);

        $designerId = $display->designer_id;
        $path = $request->file('file')->store("designers/{$designerId}/displays", 'public');
        $display->update(['background_video_url' => Storage::disk('public')->url($path)]);

        return back()->with('success', 'Video subido correctamente.');
    }

    public function uploadAudio(Request $request, DesignerDisplay $display)
    {
        $request->validate([
            'file' => 'required|max:20480',
        ]);

        $designerId = $display->designer_id;
        $path = $request->file('file')->store("designers/{$designerId}/displays", 'public');
        $display->update(['music_audio_url' => Storage::disk('public')->url($path)]);

        return back()->with('success', 'Audio subido correctamente.');
    }

    public function sendOnboardingEmail(Request $request, User $designer)
    {
        $this->authorizeDesigner($designer);

        $designer->load('eventsAsDesigner', 'designedShows.eventDay');
        $firstEvent = $designer->eventsAsDesigner?->first();

        $shows = $designer->designedShows
            ->filter(fn($show) => $show->eventDay && $show->pivot->status !== 'cancelled')
            ->sortBy([fn($a, $b) => $a->eventDay->date <=> $b->eventDay->date, fn($a, $b) => $a->scheduled_time <=> $b->scheduled_time])
            ->map(fn($show) => [
                'day_label'      => $show->eventDay->label,
                'day_date'       => $show->eventDay->date->format('Y-m-d'),
                'scheduled_time' => $show->scheduled_time,
                'show_name'      => $show->name,
            ])->values()->toArray();

        \App\Jobs\SendDesignerOnboardingJob::dispatch(
            userId:    $designer->id,
            eventName: $firstEvent?->name,
            shows:     $shows,
        );

        return back()->with('success', "Email de onboarding encolado para {$designer->full_name}.");
    }

    public function sendBulkOnboardingEmail(Request $request)
    {
        $designers = User::designers()
            ->where('status', 'pending')
            ->whereNull('welcome_email_sent_at')
            ->with(['eventsAsDesigner', 'designedShows.eventDay'])
            ->get();

        $count = 0;
        foreach ($designers as $designer) {
            $firstEvent = $designer->eventsAsDesigner?->first();

            $shows = $designer->designedShows
                ->filter(fn($show) => $show->eventDay && $show->pivot->status !== 'cancelled')
                ->sortBy([fn($a, $b) => $a->eventDay->date <=> $b->eventDay->date, fn($a, $b) => $a->scheduled_time <=> $b->scheduled_time])
                ->map(fn($show) => [
                    'day_label'      => $show->eventDay->label,
                    'day_date'       => $show->eventDay->date->format('Y-m-d'),
                    'scheduled_time' => $show->scheduled_time,
                    'show_name'      => $show->name,
                ])->values()->toArray();

            \App\Jobs\SendDesignerOnboardingJob::dispatch(
                userId:    $designer->id,
                eventName: $firstEvent?->name,
                shows:     $shows,
            );
            $count++;
        }

        return back()->with('success', "Email de onboarding encolado para {$count} diseñadores.");
    }

    public function sendOnboardingSms(Request $request, User $designer)
    {
        $this->authorizeDesigner($designer);

        if (!$designer->phone) {
            return back()->with('error', "{$designer->full_name} no tiene número de teléfono registrado.");
        }

        if (!str_starts_with($designer->phone, '+')) {
            return back()->with('error', "El teléfono de {$designer->full_name} ({$designer->phone}) debe incluir código de país (ej: +1...).");
        }

        $designer->load('eventsAsDesigner');
        $firstEvent = $designer->eventsAsDesigner?->first();

        \App\Jobs\SendDesignerOnboardingSmsJob::dispatch(
            userId:    $designer->id,
            eventName: $firstEvent?->name,
        );

        return back()->with('success', "SMS de onboarding encolado para {$designer->full_name}.");
    }

    public function sendBulkOnboardingSms(Request $request)
    {
        $designers = User::designers()
            ->where('status', 'pending')
            ->whereNotNull('phone')
            ->where('phone', 'like', '+%')
            ->whereNull('sms_sent_at')
            ->with('eventsAsDesigner')
            ->get();

        $count = 0;
        foreach ($designers as $designer) {
            $firstEvent = $designer->eventsAsDesigner?->first();

            \App\Jobs\SendDesignerOnboardingSmsJob::dispatch(
                userId:    $designer->id,
                eventName: $firstEvent?->name,
            );
            $count++;
        }

        return back()->with('success', "SMS de onboarding encolado para {$count} diseñadores.");
    }

    // --- Helpers ---

    private function authorizeDesigner(User $designer): void
    {
        abort_unless($designer->role === 'designer', 404);
    }

    private function getEventsWithShows(): \Illuminate\Support\Collection
    {
        return Event::whereIn('status', ['published', 'active', 'draft'])
            ->orderBy('start_date', 'desc')
            ->with([
                'eventDays' => fn($q) => $q->whereIn('type', ['show_day', 'fitting'])->with(['shows', 'fittingSlots']),
            ])
            ->get()
            ->map(fn(Event $event) => [
                'id'   => $event->id,
                'name' => $event->name,
                'days' => $event->eventDays->map(fn($day) => [
                    'id'    => $day->id,
                    'label' => $day->label,
                    'date'  => $day->date?->format('Y-m-d'),
                    'type'  => $day->type,
                    'shows' => $day->shows->map(fn($show) => [
                        'id'   => $show->id,
                        'name' => $show->name,
                    ])->values(),
                    'fitting_slots' => $day->fittingSlots->map(fn($slot) => [
                        'id'   => $slot->id,
                        'time' => $slot->time,
                    ])->values(),
                ])->values(),
            ]);
    }

    private function formatDesignerForView(User $designer): array
    {
        $profile = $designer->designerProfile;
        $passMap = $designer->eventPasses->keyBy('event_id');

        // Build day map: day_id -> label across all designer events
        $dayMap = [];
        foreach ($designer->eventsAsDesigner ?? [] as $evt) {
            foreach ($evt->eventDays ?? [] as $day) {
                $dayMap[$day->id] = $day->label;
            }
        }

        // Modelos asignadas al diseñador por evento
        $modelRows = \DB::table('show_model')
            ->join('shows', 'shows.id', '=', 'show_model.show_id')
            ->join('event_days', 'event_days.id', '=', 'shows.event_day_id')
            ->join('users', 'users.id', '=', 'show_model.model_id')
            ->leftJoin('event_model', function ($j) {
                $j->on('event_model.model_id', '=', 'show_model.model_id')
                  ->on('event_model.event_id', '=', 'event_days.event_id');
            })
            ->where('show_model.designer_id', $designer->id)
            ->whereIn('show_model.status', ['confirmed', 'reserved', 'requested'])
            ->select(
                'event_days.event_id',
                'users.id as model_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.profile_picture',
                'event_model.participation_number',
                'show_model.status',
            )
            ->distinct()
            ->get();

        $modelsByEvent = $modelRows->groupBy('event_id')->map(fn($rows) =>
            $rows->unique('model_id')->values()
        );

        $modelCountsByEvent = $modelsByEvent->map(fn($rows) => $rows->count());

        return array_merge($designer->toArray(), [
            'designer_profile' => $profile ? array_merge($profile->toArray(), [
                'category' => $profile->category,
                'sales_rep' => $profile->salesRep ? [
                    'id' => $profile->salesRep->id,
                    'first_name' => $profile->salesRep->first_name,
                    'last_name' => $profile->salesRep->last_name,
                ] : null,
            ]) : null,
            'events' => $designer->eventsAsDesigner?->map(fn($event) => [
                'id'                    => $event->id,
                'name'                  => $event->name,
                'status'                => $event->status,
                'package_id'            => $event->pivot->package_id,
                'looks'                 => $event->pivot->looks,
                'model_casting_enabled' => $event->pivot->model_casting_enabled,
                'package_price'         => $event->pivot->package_price,
                'notes'                 => $event->pivot->notes,
                'designer_status'       => $event->pivot->status,
                'models_count'          => $modelCountsByEvent[$event->id] ?? 0,
                'assigned_models'       => ($modelsByEvent[$event->id] ?? collect())->map(fn($m) => [
                    'id'                   => $m->model_id,
                    'first_name'           => $m->first_name,
                    'last_name'            => $m->last_name,
                    'email'                => $m->email,
                    'profile_picture'      => $m->profile_picture,
                    'participation_number' => $m->participation_number,
                    'status'               => $m->status,
                ])->values()->toArray(),
                'pass'                  => $passMap->has($event->id) ? (function () use ($passMap, $event, $dayMap) {
                    $p = $passMap[$event->id];
                    return [
                        'qr_code'           => $p->qr_code,
                        'status'            => $p->status,
                        'pass_type'         => $p->pass_type,
                        'pass_type_label'   => $p->passTypeLabel(),
                        'holder_name'       => $p->holder_name,
                        'holder_email'      => $p->holder_email,
                        'valid_days'        => $p->valid_days,
                        'valid_days_labels' => $p->valid_days
                            ? collect($p->valid_days)->map(fn($id) => $dayMap[$id] ?? null)->filter()->join(' · ')
                            : null,
                    ];
                })() : null,
            ])->values(),
            'shows' => $designer->designedShows?->map(fn($show) => [
                'id'              => $show->id,
                'name'            => $show->name,
                'order'           => $show->pivot->order,
                'collection_name' => $show->pivot->collection_name,
                'status'          => $show->pivot->status,
                'notes'           => $show->pivot->notes,
                'event_day'       => $show->eventDay ? [
                    'id'       => $show->eventDay->id,
                    'event_id' => $show->eventDay->event_id,
                    'label'    => $show->eventDay->label,
                    'date'     => $show->eventDay->date?->format('Y-m-d'),
                ] : null,
            ])->values(),
            'assistants' => $designer->designerAssistants?->map(fn($a) => [
                'id'          => $a->id,
                'event_id'    => $a->event_id,
                'full_name'   => $a->full_name,
                'document_id' => $a->document_id,
                'phone'       => $a->phone,
                'email'       => $a->email,
                'status'      => $a->status,
            ])->values(),
            'materials' => $designer->designerMaterials?->map(fn($m) => [
                'id'          => $m->id,
                'event_id'    => $m->event_id,
                'show_id'     => $m->show_id,
                'name'        => $m->name,
                'description' => $m->description,
                'drive_link'  => $m->drive_link,
                'status'      => $m->status,
                'type'        => $m->type,
            ])->values(),
            'displays' => $designer->designerDisplays?->map(fn($d) => [
                'id'                   => $d->id,
                'event_id'             => $d->event_id,
                'show_id'              => $d->show_id,
                'background_video_url' => $d->background_video_url,
                'music_audio_url'      => $d->music_audio_url,
                'status'               => $d->status,
                'notes'                => $d->notes,
            ])->values(),
            'fittings' => $designer->fittingAssignments?->map(fn($fa) => [
                'id'             => $fa->id,
                'fitting_slot_id'=> $fa->fitting_slot_id,
                'event_id'       => $fa->fittingSlot?->eventDay?->event_id,
                'event_day_id'   => $fa->fittingSlot?->event_day_id,
                'day_label'      => $fa->fittingSlot?->eventDay?->label,
                'day_date'       => $fa->fittingSlot?->eventDay?->date?->format('Y-m-d'),
                'time'           => $fa->fittingSlot?->time,
                'notes'          => $fa->notes,
            ])->values(),
        ]);
    }
}
