<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ModelsExport;
use App\Http\Controllers\Controller;
use App\Imports\ModelsImport;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\Event;
use App\Models\FittingAssignment;
use App\Models\User;
use App\Services\ModelService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModelController extends Controller
{
    public function __construct(protected ModelService $modelService) {}

    public function index(Request $request): Response
    {
        $query = User::models()->with([
            'modelProfile',
            'eventsAsModelWithCasting',
        ]);

        if ($request->filled('event')) {
            $query->whereHas('eventsAsModelWithCasting', fn($q) => $q->where('events.id', $request->event));
        }

        if ($request->filled('compcard')) {
            $query->whereHas('modelProfile', function ($q) use ($request) {
                if ($request->compcard === 'complete') {
                    $q->where('compcard_completed', true);
                } elseif ($request->compcard === 'incomplete') {
                    $q->where('compcard_completed', false);
                }
            });
        }

        if ($request->filled('gender')) {
            $query->whereHas('modelProfile', fn($q) => $q->where('gender', $request->gender));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name',  'ilike', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"])
                  ->orWhere('email',      'ilike', "%{$search}%")
                  ->orWhere('phone',      'ilike', "%{$search}%")
                  ->orWhereHas('eventsAsModelWithCasting', fn($eq) =>
                      $eq->where('event_model.participation_number', 'like', "%{$search}%")
                  );
            });
        }

        if ($request->filled('email_sent')) {
            if ($request->email_sent === 'sent') {
                $query->whereNotNull('welcome_email_sent_at');
            } elseif ($request->email_sent === 'not_sent') {
                $query->whereNull('welcome_email_sent_at');
            }
        }

        if ($request->filled('test_model')) {
            if ($request->test_model === 'only_test') {
                $query->whereHas('modelProfile', fn($q) => $q->where('is_test_model', true));
            } elseif ($request->test_model === 'only_real') {
                $query->where(function ($q) {
                    $q->whereHas('modelProfile', fn($pq) => $pq->where('is_test_model', false))
                      ->orWhereDoesntHave('modelProfile');
                });
            }
        }

        if ($request->filled('casting_time')) {
            $castingFilter = $request->casting_time;
            $query->whereHas('eventsAsModelWithCasting', fn($q) =>
                $q->whereRaw("TO_CHAR(event_model.casting_time, 'HH24:MI') = ?", [$castingFilter])
            );
        }

        if ($request->filled('casting_status')) {
            $query->whereHas('eventsAsModelWithCasting', fn($q) =>
                $q->where('event_model.casting_status', $request->casting_status)
            );
        }

        if ($request->filled('designer')) {
            $designerFilter = (int) $request->designer;
            $query->whereIn('users.id', function ($sub) use ($designerFilter) {
                $sub->select('show_model.model_id')
                    ->from('show_model')
                    ->where('show_model.designer_id', $designerFilter)
                    ->whereIn('show_model.status', ['confirmed', 'reserved', 'requested']);
            });
        }

        $models = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Batch-load fitting data for models on this page
        $modelIds = $models->pluck('id');

        // Get designer-event pairs for each model via show_model
        $showModelRows = DB::table('show_model')
            ->join('shows', 'shows.id', '=', 'show_model.show_id')
            ->join('event_days', 'event_days.id', '=', 'shows.event_day_id')
            ->whereIn('show_model.model_id', $modelIds)
            ->whereIn('show_model.status', ['confirmed', 'reserved', 'requested'])
            ->select('show_model.model_id', 'show_model.designer_id', 'event_days.event_id')
            ->distinct()
            ->get();

        // Get fitting assignments for all relevant designers
        $designerIds = $showModelRows->pluck('designer_id')->unique()->filter();
        $fittingsByDesignerEvent = [];

        if ($designerIds->isNotEmpty()) {
            $assignments = FittingAssignment::whereIn('designer_id', $designerIds)
                ->with(['fittingSlot.eventDay', 'designer.designerProfile'])
                ->get();

            foreach ($assignments as $a) {
                $eventId = $a->fittingSlot->eventDay?->event_id;
                if ($eventId) {
                    $key = $a->designer_id . '-' . $eventId;
                    $fittingsByDesignerEvent[$key] = [
                        'day_label'     => $a->fittingSlot->eventDay->label,
                        'time'          => $a->fittingSlot->time,
                        'designer_name' => $a->designer?->full_name,
                        'brand_name'    => $a->designer?->designerProfile?->brand_name,
                    ];
                }
            }
        }

        // Build fittings map: model_id -> event_id -> fittings[]
        $modelFittingsMap = [];
        foreach ($showModelRows as $row) {
            $key = $row->designer_id . '-' . $row->event_id;
            if (isset($fittingsByDesignerEvent[$key])) {
                $modelFittingsMap[$row->model_id][$row->event_id][] = $fittingsByDesignerEvent[$key];
            }
        }

        // Inject fittings_by_event into each model
        $models->through(function ($model) use ($modelFittingsMap) {
            $model->fittings_by_event = $modelFittingsMap[$model->id] ?? [];
            return $model;
        });

        $events = Event::orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        $pendingEmailCount = User::models()
            ->whereNull('welcome_email_sent_at')
            ->count();

        // Obtener horarios de casting desde casting_slots (solo cuando hay evento seleccionado)
        $castingTimes = collect();
        if ($request->filled('event')) {
            $castingTimes = DB::table('casting_slots')
                ->join('event_days', 'event_days.id', '=', 'casting_slots.event_day_id')
                ->where('event_days.type', 'casting')
                ->where('event_days.event_id', $request->event)
                ->distinct()
                ->pluck('casting_slots.time')
                ->map(fn($t) => \Illuminate\Support\Str::substr($t, 0, 5))
                ->sort()
                ->values();
        }

        // Designers list for filter (filtered by event if selected)
        $designersQuery = User::where('role', 'designer')
            ->with('designerProfile:id,user_id,brand_name');

        if ($request->filled('event')) {
            $designersQuery->whereHas('eventsAsDesigner', fn($q) => $q->where('events.id', $request->event));
        }

        $designers = $designersQuery->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn($d) => [
                'id'         => $d->id,
                'name'       => $d->full_name,
                'brand_name' => $d->designerProfile?->brand_name,
            ]);

        return Inertia::render('Admin/Models/Index', [
            'models'             => $models,
            'events'             => $events,
            'designers'          => $designers,
            'filters'            => $request->only(['event', 'compcard', 'gender', 'search', 'email_sent', 'test_model', 'casting_time', 'casting_status', 'designer']),
            'castingTimes'       => $castingTimes,
            'pendingEmailCount'  => $pendingEmailCount,
        ]);
    }

    public function create(): Response
    {
        $events = $this->getEventsWithCastingSlots();

        return Inertia::render('Admin/Models/Create', [
            'events' => $events,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => 'required|email|unique:users',
            'phone'       => 'nullable|string|unique:users',
            'instagram'   => 'nullable|string|max:255',
            'age'         => 'nullable|integer|min:16|max:80',
            'gender'      => 'nullable|in:female,male,non_binary',
            'location'    => 'nullable|string|max:255',
            'ethnicity'   => 'nullable|in:asian,black,caucasian,hispanic,middle_eastern,mixed,other',
            'hair'        => 'nullable|in:black,brown,blonde,red,gray,other',
            'body_type'   => 'nullable|in:slim,athletic,average,curvy,plus_size',
            'height'      => 'nullable|numeric',
            'bust'        => 'nullable|numeric',
            'chest'       => 'nullable|string|max:50',
            'waist'       => 'nullable|numeric',
            'hips'        => 'nullable|numeric',
            'shoe_size'   => 'nullable|string|max:20',
            'dress_size'  => 'nullable|string|max:20',
            'agency'      => 'nullable|string|max:255',
            'is_agency'   => 'boolean',
            'is_test_model' => 'boolean',
            'notes'       => 'nullable|string',
            'event_id'    => 'nullable|exists:events,id',
            'casting_time'=> 'nullable|string',
            'send_welcome_email' => 'boolean',
        ]);

        $userData = $request->only(['first_name', 'last_name', 'email', 'phone']);
        $profileData = $request->only([
            'instagram', 'age', 'gender', 'location', 'ethnicity', 'hair', 'body_type',
            'height', 'bust', 'chest', 'waist', 'hips', 'shoe_size', 'dress_size',
            'agency', 'is_agency', 'is_test_model', 'notes',
        ]);

        $model = $this->modelService->createModel(
            $userData,
            $profileData,
            $request->event_id,
            $request->casting_time,
        );

        if ($request->filled('event_id')) {
            $this->modelService->syncModelPass($model, $request->event_id, $request->user()->id);
        }

        if ($request->boolean('send_welcome_email')) {
            $this->modelService->sendWelcomeEmail($model);
        }

        return redirect()->route('admin.models.show', $model)
            ->with('success', 'Modelo creada exitosamente.');
    }

    public function show(User $model): Response
    {
        $this->authorizeModel($model);

        $model->load([
            'modelProfile',
            'eventsAsModelWithCasting.eventDays',
            'shows' => fn($q) => $q->with(['eventDay.event', 'designers.designerProfile']),
            'eventPasses',
        ]);

        // Auto-generar pases faltantes para eventos asignados
        $existingPassEventIds = $model->eventPasses->pluck('event_id')->toArray();
        foreach ($model->eventsAsModelWithCasting ?? [] as $event) {
            if (!in_array($event->id, $existingPassEventIds)) {
                $this->modelService->syncModelPass($model, $event->id, auth()->id());
            }
        }

        // Recargar pases si se generaron nuevos
        if (count($existingPassEventIds) < ($model->eventsAsModelWithCasting?->count() ?? 0)) {
            $model->load('eventPasses');
        }

        return Inertia::render('Admin/Models/Show', [
            'model' => $this->formatModelForView($model),
        ]);
    }

    public function edit(User $model): Response
    {
        $this->authorizeModel($model);

        $model->load('modelProfile', 'eventsAsModelWithCasting');
        $events = $this->getEventsWithCastingSlots();

        return Inertia::render('Admin/Models/Edit', [
            'model'  => $this->formatModelForView($model),
            'events' => $events,
        ]);
    }

    public function update(Request $request, User $model)
    {
        $this->authorizeModel($model);

        $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'email'       => "required|email|unique:users,email,{$model->id}",
            'phone'       => "nullable|string|unique:users,phone,{$model->id}",
            'status'      => 'nullable|in:inactive,pending',
            'instagram'   => 'nullable|string|max:255',
            'age'         => 'nullable|integer|min:16|max:80',
            'gender'      => 'nullable|in:female,male,non_binary',
            'location'    => 'nullable|string|max:255',
            'ethnicity'   => 'nullable|in:asian,black,caucasian,hispanic,middle_eastern,mixed,other',
            'hair'        => 'nullable|in:black,brown,blonde,red,gray,other',
            'body_type'   => 'nullable|in:slim,athletic,average,curvy,plus_size',
            'height'      => 'nullable|numeric',
            'bust'        => 'nullable|numeric',
            'chest'       => 'nullable|string|max:50',
            'waist'       => 'nullable|numeric',
            'hips'        => 'nullable|numeric',
            'shoe_size'   => 'nullable|string|max:20',
            'dress_size'  => 'nullable|string|max:20',
            'agency'      => 'nullable|string|max:255',
            'is_agency'   => 'boolean',
            'is_test_model' => 'boolean',
            'notes'       => 'nullable|string',
        ]);

        $userData = $request->only(['first_name', 'last_name', 'email', 'phone']);
        $profileData = $request->only([
            'instagram', 'age', 'gender', 'location', 'ethnicity', 'hair', 'body_type',
            'height', 'bust', 'chest', 'waist', 'hips', 'shoe_size', 'dress_size',
            'agency', 'is_agency', 'is_test_model', 'notes',
        ]);

        $userData['status'] = $request->input('status', $model->status);
        $this->modelService->updateModel($model, $userData, $profileData);

        return redirect()->route('admin.models.show', $model)
            ->with('success', 'Modelo actualizada exitosamente.');
    }

    public function destroy(User $model)
    {
        $this->authorizeModel($model);

        try {
            $this->modelService->deleteModel($model);
        } catch (\Exception $e) {
            return redirect()->route('admin.models.index')
                ->withErrors(['delete' => 'No se pudo eliminar la modelo: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.models.index')
            ->with('success', 'Modelo eliminada correctamente.');
    }

    public function assignEvent(Request $request, User $model)
    {
        $this->authorizeModel($model);

        $request->validate([
            'event_id'     => 'required|exists:events,id',
            'casting_time' => 'nullable|string',
        ]);

        try {
            $this->modelService->assignToEvent($model, $request->event_id, $request->casting_time);
            $this->modelService->syncModelPass($model, $request->event_id, $request->user()->id);
            return back()->with('success', 'Modelo asignada al evento.');
        } catch (\Exception $e) {
            return back()->withErrors(['event' => $e->getMessage()]);
        }
    }

    public function removeEvent(User $model, Event $event)
    {
        $this->authorizeModel($model);

        try {
            $this->modelService->removeFromEvent($model, $event->id);
            return back()->with('success', 'Modelo removida del evento.');
        } catch (\Exception $e) {
            return back()->withErrors(['event' => $e->getMessage()]);
        }
    }

    public function uploadPhoto(Request $request, User $model, int $position)
    {
        $this->authorizeModel($model);

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        try {
            $path = $this->modelService->uploadCompCardPhoto($model, $position, $request->file('photo'));
            return back()->with('success', "Foto {$position} subida correctamente.");
        } catch (\Exception $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }
    }

    public function deletePhoto(User $model, int $position)
    {
        $this->authorizeModel($model);

        try {
            $this->modelService->deleteCompCardPhoto($model, $position);
            return back()->with('success', "Foto {$position} eliminada.");
        } catch (\Exception $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }
    }

    public function uploadProfilePicture(Request $request, User $model)
    {
        $this->authorizeModel($model);

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        try {
            $this->modelService->uploadProfilePicture($model, $request->file('photo'));
            return back()->with('success', 'Foto de perfil actualizada.');
        } catch (\Exception $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }
    }

    public function deleteProfilePicture(User $model)
    {
        $this->authorizeModel($model);

        try {
            $this->modelService->deleteProfilePicture($model);
            return back()->with('success', 'Foto de perfil eliminada.');
        } catch (\Exception $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }
    }

    public function sendWelcomeEmail(User $model)
    {
        $this->authorizeModel($model);

        // Obtener primer evento asignado para incluir datos en el email
        $model->load(['eventsAsModelWithCasting.eventDays' => fn($q) => $q->where('type', 'casting')]);
        $firstEvent  = $model->eventsAsModelWithCasting?->first();
        $castingDay  = $firstEvent?->eventDays?->first();

        SendWelcomeEmailJob::dispatch(
            userId:      $model->id,
            eventName:   $firstEvent?->name,
            castingTime: $firstEvent?->pivot?->casting_time,
            castingDate: $castingDay?->date?->format('Y-m-d'),
        );

        return back()->with('success', 'Email de bienvenida encolado para envío.');
    }

    public function exportModels(Request $request)
    {
        $filename = 'modelos_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ModelsExport(
            search:    $request->input('search'),
            event:     $request->input('event'),
            compcard:  $request->input('compcard'),
            gender:    $request->input('gender'),
            emailSent: $request->input('email_sent'),
            testModel: $request->input('test_model'),
        ), $filename);
    }

    public function importModels(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'event_id' => 'nullable|exists:events,id',
        ]);

        $eventId = $request->filled('event_id') ? (int) $request->event_id : null;
        $import  = new ModelsImport(globalEventId: $eventId);
        Excel::import($import, $request->file('file'));

        $s = $import->summary;
        $msg = "Importación completada: {$s['created']} creadas, {$s['updated']} actualizadas, {$s['assigned']} asignadas a eventos.";

        if (!empty($s['errors'])) {
            $msg .= ' ' . count($s['errors']) . ' errores (ver log).';
            \Illuminate\Support\Facades\Log::warning('ModelsImport errors', $s['errors']);
        }

        return back()->with('success', $msg)->with('importSummary', $s);
    }

    public function sendPendingWelcomeEmails()
    {
        $pending = User::models()
            ->whereNull('welcome_email_sent_at')
            ->with(['eventsAsModelWithCasting.eventDays' => fn($q) => $q->where('type', 'casting')])
            ->get();

        if ($pending->isEmpty()) {
            return back()->with('info', 'No hay modelos pendientes de recibir correo.');
        }

        $count = 0;
        foreach ($pending as $model) {
            $firstEvent = $model->eventsAsModelWithCasting?->first();
            $castingDay = $firstEvent?->eventDays?->first();

            SendWelcomeEmailJob::dispatch(
                userId:      $model->id,
                eventName:   $firstEvent?->name,
                castingTime: $firstEvent?->pivot?->casting_time,
                castingDate: $castingDay?->date?->format('Y-m-d'),
            );
            $count++;
        }

        return back()->with('success', "{$count} emails encolados para envío. Se procesarán en los próximos minutos.");
    }

    public function updateStatus(Request $request, User $model)
    {
        $this->authorizeModel($model);

        $request->validate(['status' => 'required|in:inactive,pending']);

        $model->update(['status' => $request->status]);

        return back()->with('success', 'Estado actualizado.');
    }

    // --- Helpers ---

    private function authorizeModel(User $model): void
    {
        abort_unless($model->role === 'model', 404);
    }

    private function getEventsWithCastingSlots(): \Illuminate\Support\Collection
    {
        return Event::whereIn('status', ['published', 'active', 'draft'])
            ->orderBy('start_date', 'desc')
            ->with(['eventDays' => fn($q) => $q->where('type', 'casting')->with('castingSlots')])
            ->get()
            ->map(fn(Event $event) => [
                'id'          => $event->id,
                'name'        => $event->name,
                'casting_day' => $event->eventDays->first() ? [
                    'id'    => $event->eventDays->first()->id,
                    'date'  => $event->eventDays->first()->date?->format('Y-m-d'),
                    'slots' => $event->eventDays->first()->castingSlots->map(fn($slot) => [
                        'id'        => $slot->id,
                        'time'      => $slot->time,
                        'capacity'  => $slot->capacity,
                        'booked'    => $slot->booked,
                        'available' => $slot->availableSpots(),
                    ])->values(),
                ] : null,
            ]);
    }

    private function formatModelForView(User $model): array
    {
        $profile = $model->modelProfile;
        $passMap = $model->eventPasses->keyBy('event_id');

        // Build day map: day_id -> label across all model events
        $dayMap = [];
        foreach ($model->eventsAsModelWithCasting ?? [] as $evt) {
            foreach ($evt->eventDays ?? [] as $day) {
                $dayMap[$day->id] = $day->label;
            }
        }

        $data = array_merge($model->toArray(), [
            'model_profile'  => $profile ? array_merge($profile->toArray(), [
                'comp_card_progress' => $profile->comp_card_progress,
                'comp_card_photos'   => $profile->compCardPhotos,
            ]) : null,
            'events' => $model->eventsAsModelWithCasting?->map(fn($event) => [
                'id'                    => $event->id,
                'name'                  => $event->name,
                'status'                => $event->status,
                'casting_time'          => $event->pivot->casting_time,
                'casting_status'        => $event->pivot->casting_status,
                'casting_checked_in_at' => $event->pivot->casting_checked_in_at,
                'participation_number'  => $event->pivot->participation_number,
                'model_status'          => $event->pivot->status,
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
            'shows' => $model->shows?->map(fn($show) => [
                'id'             => $show->id,
                'name'           => $show->name,
                'formatted_time' => $show->formatted_time,
                'status'         => $show->pivot->status,
                'walk_order'     => $show->pivot->walk_order,
                'requested_at'   => $show->pivot->requested_at,
                'responded_at'   => $show->pivot->responded_at,
                'event'          => $show->eventDay?->event ? [
                    'id'   => $show->eventDay->event->id,
                    'name' => $show->eventDay->event->name,
                ] : null,
                'event_day' => $show->eventDay ? [
                    'id'    => $show->eventDay->id,
                    'label' => $show->eventDay->label,
                    'date'  => $show->eventDay->date?->format('Y-m-d'),
                ] : null,
                'designers' => $show->designers?->map(fn($d) => [
                    'id'         => $d->id,
                    'name'       => $d->full_name,
                    'brand_name' => $d->designerProfile?->brand_name,
                ])->values(),
            ])->values(),
            'fittings' => $this->getModelFittings($model),
        ]);

        return $data;
    }

    /**
     * Get fitting schedule for a model (inherited from their designers).
     */
    private function getModelFittings(User $model): array
    {
        $fittings = [];

        foreach ($model->shows ?? [] as $show) {
            if (!$show->eventDay?->event) continue;
            $eventId = $show->eventDay->event->id;
            $eventName = $show->eventDay->event->name;

            // Obtener designer_ids que seleccionaron a esta modelo en show_model
            $designerIds = DB::table('show_model')
                ->where('show_id', $show->id)
                ->where('model_id', $model->id)
                ->whereIn('status', ['confirmed', 'reserved', 'requested'])
                ->pluck('designer_id')
                ->unique()
                ->filter();

            if ($designerIds->isEmpty()) {
                // Fallback: use designers from the show pivot
                $designerIds = $show->designers?->pluck('id') ?? collect();
            }

            foreach ($designerIds as $designerId) {
                $assignment = FittingAssignment::whereHas('fittingSlot.eventDay', fn($q) => $q->where('event_id', $eventId))
                    ->where('designer_id', $designerId)
                    ->with(['fittingSlot.eventDay', 'designer.designerProfile'])
                    ->first();

                if ($assignment) {
                    $fittings[] = [
                        'event_id'      => $eventId,
                        'event_name'    => $eventName,
                        'day_label'     => $assignment->fittingSlot->eventDay?->label,
                        'day_date'      => $assignment->fittingSlot->eventDay?->date?->format('Y-m-d'),
                        'time'          => $assignment->fittingSlot->time,
                        'designer_name' => $assignment->designer?->full_name,
                        'brand_name'    => $assignment->designer?->designerProfile?->brand_name,
                    ];
                }
            }
        }

        // Deduplicate by event_id + designer_name
        return collect($fittings)->unique(fn($f) => $f['event_id'] . '-' . $f['designer_name'])->values()->toArray();
    }
}
