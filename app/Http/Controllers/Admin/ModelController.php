<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Exports\ModelsExport;
use App\Http\Controllers\Controller;
use App\Imports\ModelsImport;
use App\Jobs\SendModelOnboardingJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\FittingAssignment;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\ModelService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModelController extends Controller
{
    public function __construct(
        protected ModelService $modelService,
        protected ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): Response
    {
        $query = User::models()->with([
            'modelProfile',
            'eventsAsModelWithCasting',
            'communicationLogs' => fn($q) => $q->whereIn('channel', ['welcome_email', 'registration_email', 'model_onboarding'])->with('sender')->orderByDesc('created_at'),
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

        if ($request->filled('ethnicity')) {
            $query->whereHas('modelProfile', fn($q) => $q->where('ethnicity', $request->ethnicity));
        }

        if ($request->filled('is_agency')) {
            $query->whereHas('modelProfile', fn($q) => $q->where('is_agency', $request->is_agency === 'yes'));
        }

        if ($request->filled('is_top')) {
            $query->whereHas('modelProfile', fn($q) => $q->where('is_top', $request->is_top === 'yes'));
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

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('registered_from')) {
            $query->whereDate('users.created_at', '>=', $request->registered_from);
        }
        if ($request->filled('registered_to')) {
            $query->whereDate('users.created_at', '<=', $request->registered_to);
        }

        if ($request->filled('checkin_from')) {
            $query->whereHas('eventsAsModelWithCasting', fn($q) =>
                $q->whereDate('event_model.casting_checked_in_at', '>=', $request->checkin_from)
            );
        }
        if ($request->filled('checkin_to')) {
            $query->whereHas('eventsAsModelWithCasting', fn($q) =>
                $q->whereDate('event_model.casting_checked_in_at', '<=', $request->checkin_to)
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

        if ($request->filled('merch')) {
            if ($request->merch === 'with') {
                $query->whereHas('eventsAsModelWithCasting', fn($q) =>
                    $q->whereNotNull('event_model.shopify_order_number')
                );
            } elseif ($request->merch === 'without') {
                $query->whereDoesntHave('eventsAsModelWithCasting', fn($q) =>
                    $q->whereNotNull('event_model.shopify_order_number')
                );
            }
        }

        if ($request->filled('model_kit')) {
            $query->whereHas('modelProfile', function ($q) use ($request) {
                match ($request->model_kit) {
                    'wants'     => $q->where('wants_model_kit', true)->whereNull('model_kit_paid_at'),
                    'not_wants' => $q->where('wants_model_kit', false),
                    'paid'      => $q->whereNotNull('model_kit_paid_at'),
                    default     => null,
                };
            });
        }

        if ($request->filled('sort_name')) {
            $dir = $request->sort_name === 'asc' ? 'asc' : 'desc';
            $query->orderBy('first_name', $dir)->orderBy('last_name', $dir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = in_array((int) $request->input('per_page'), [20, 50, 100, 200, 500]) ? (int) $request->input('per_page') : 20;
        $models = $query->paginate($perPage)->withQueryString();

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

        // Batch-load shows data for models on this page
        $modelShowsMap = [];
        if ($modelIds->isNotEmpty()) {
            $showRows = DB::table('show_model')
                ->join('shows', 'shows.id', '=', 'show_model.show_id')
                ->join('event_days', 'event_days.id', '=', 'shows.event_day_id')
                ->leftJoin('users as designer_user', 'designer_user.id', '=', 'show_model.designer_id')
                ->leftJoin('designer_profiles', 'designer_profiles.user_id', '=', 'show_model.designer_id')
                ->whereIn('show_model.model_id', $modelIds)
                ->whereIn('show_model.status', ['confirmed', 'reserved', 'requested'])
                ->select(
                    'show_model.model_id',
                    'show_model.show_id',
                    'show_model.designer_id',
                    'show_model.status as model_status',
                    'shows.name as show_name',
                    'shows.scheduled_time',
                    'event_days.event_id',
                    'event_days.label as day_label',
                    'designer_user.first_name as designer_first_name',
                    'designer_user.last_name as designer_last_name',
                    'designer_profiles.brand_name',
                )
                ->orderBy('event_days.date')
                ->orderBy('shows.scheduled_time')
                ->get();

            foreach ($showRows as $row) {
                $time = $row->scheduled_time;
                try {
                    $time = \Illuminate\Support\Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
                } catch (\Exception $e) {
                    try { $time = \Illuminate\Support\Carbon::createFromFormat('H:i', $time)->format('g:i A'); } catch (\Exception $e2) {}
                }

                $modelShowsMap[$row->model_id][$row->event_id][] = [
                    'show_id'        => $row->show_id,
                    'show_name'      => $row->show_name,
                    'day_label'      => $row->day_label,
                    'formatted_time' => $time,
                    'status'         => $row->model_status,
                    'designer_name'  => trim(($row->designer_first_name ?? '') . ' ' . ($row->designer_last_name ?? '')) ?: null,
                    'brand_name'     => $row->brand_name,
                ];
            }
        }

        // Inject fittings_by_event and shows_by_event into each model
        $models->through(function ($model) use ($modelFittingsMap, $modelShowsMap) {
            $model->fittings_by_event = $modelFittingsMap[$model->id] ?? [];
            $model->shows_by_event = $modelShowsMap[$model->id] ?? [];
            return $model;
        });

        // --- Stats cards ---
        $statsBaseQuery = User::models();
        if ($request->filled('event')) {
            $statsBaseQuery->whereHas('eventsAsModelWithCasting', fn($q) => $q->where('events.id', $request->event));
        }
        $statsBaseIds = (clone $statsBaseQuery)->pluck('users.id');

        $totalModels = $statsBaseIds->count();

        // Merch: tienen shopify_order_number en event_model
        $merchQuery = DB::table('event_model')->whereIn('model_id', $statsBaseIds)->whereNotNull('shopify_order_number');
        if ($request->filled('event')) {
            $merchQuery->where('event_id', $request->event);
        }
        $merchCount = $merchQuery->distinct('model_id')->count('model_id');

        // Agencia, Top, Model Kit via model_profiles
        $profileStats = DB::table('model_profiles')
            ->whereIn('user_id', $statsBaseIds)
            ->selectRaw("COUNT(*) FILTER (WHERE is_agency = true) as agency_count")
            ->selectRaw("COUNT(*) FILTER (WHERE is_top = true) as top_count")
            ->selectRaw("COUNT(*) FILTER (WHERE gender = 'male') as male_count")
            ->selectRaw("COUNT(*) FILTER (WHERE gender = 'female') as female_count")
            ->selectRaw("COUNT(*) FILTER (WHERE wants_model_kit = true AND model_kit_paid_at IS NULL) as kit_wants_count")
            ->selectRaw("COUNT(*) FILTER (WHERE wants_model_kit = false OR wants_model_kit IS NULL) as kit_not_wants_count")
            ->selectRaw("COUNT(*) FILTER (WHERE model_kit_paid_at IS NOT NULL) as kit_paid_count")
            ->first();

        $agencyCount  = (int) ($profileStats->agency_count ?? 0);
        $topCount     = (int) ($profileStats->top_count ?? 0);
        $maleCount    = (int) ($profileStats->male_count ?? 0);
        $femaleCount  = (int) ($profileStats->female_count ?? 0);
        $normalCount  = $totalModels - $merchCount - $agencyCount;
        if ($normalCount < 0) $normalCount = 0;

        // Checkin: modelos que hicieron check-in en casting
        $checkinQuery = DB::table('event_model')->whereIn('model_id', $statsBaseIds)->whereNotNull('casting_checked_in_at');
        if ($request->filled('event')) {
            $checkinQuery->where('event_id', $request->event);
        }
        $checkinCount = $checkinQuery->distinct('model_id')->count('model_id');

        // Conteo por user.status
        $statusCounts = DB::table('users')
            ->whereIn('id', $statsBaseIds)
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'active') as active_count")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'pending') as pending_count")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'applicant') as applicant_count")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'rejected') as rejected_count")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'inactive') as inactive_count")
            ->first();

        $stats = [
            'total'     => $totalModels,
            'merch'     => $merchCount,
            'agency'    => $agencyCount,
            'top'       => $topCount,
            'normal'    => $normalCount,
            'male'      => $maleCount,
            'female'    => $femaleCount,
            'checkin'   => $checkinCount,
            'active'    => (int) ($statusCounts->active_count ?? 0),
            'pending'   => (int) ($statusCounts->pending_count ?? 0),
            'applicant' => (int) ($statusCounts->applicant_count ?? 0),
            'rejected'  => (int) ($statusCounts->rejected_count ?? 0),
            'inactive'  => (int) ($statusCounts->inactive_count ?? 0),
            'kit_wants'     => (int) ($profileStats->kit_wants_count ?? 0),
            'kit_not_wants' => (int) ($profileStats->kit_not_wants_count ?? 0),
            'kit_paid'      => (int) ($profileStats->kit_paid_count ?? 0),
        ];

        $events = Event::orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        $pendingEmailCount = User::models()
            ->where('status', 'pending')
            ->whereNull('welcome_email_sent_at')
            ->whereHas('eventsAsModelWithCasting', fn($q) => $q->whereNotNull('event_model.casting_time'))
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
            'filters'            => $request->only(['event', 'compcard', 'gender', 'ethnicity', 'is_agency', 'is_top', 'search', 'email_sent', 'test_model', 'casting_time', 'casting_status', 'designer', 'status', 'merch', 'model_kit', 'per_page']),
            'castingTimes'       => $castingTimes,
            'pendingEmailCount'  => $pendingEmailCount,
            'stats'              => $stats,
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
        $this->sanitizeInstagram($request);

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
            'referral_source'       => 'nullable|in:instagram,tiktok,facebook,friends_family,agency,other',
            'referral_source_other' => 'nullable|string|max:255',
            'walk_video_url'        => 'nullable|url|max:500',
            'wants_model_kit'       => 'nullable|boolean',
            'model_kit_paid_at'     => 'nullable|date',
            'event_id'    => 'nullable|exists:events,id',
            'casting_time'=> 'nullable|string',
        ]);

        $userData = $request->only(['first_name', 'last_name', 'email', 'phone']);
        $profileData = $request->only([
            'instagram', 'age', 'gender', 'location', 'ethnicity', 'hair', 'body_type',
            'height', 'bust', 'chest', 'waist', 'hips', 'shoe_size', 'dress_size',
            'agency', 'is_agency', 'is_test_model', 'notes',
            'referral_source', 'referral_source_other', 'walk_video_url', 'wants_model_kit', 'model_kit_paid_at',
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


        $this->activityLog->log(
            ActivityAction::Registered, $model, $request->user(),
            "Modelo creada desde admin: {$model->first_name} {$model->last_name}"
        );

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
        $this->sanitizeInstagram($request);

        $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'nullable|string|max:255',
            'email'       => "required|email|unique:users,email,{$model->id}",
            'phone'       => "nullable|string|unique:users,phone,{$model->id}",
            'status'      => 'nullable|in:inactive,pending,applicant',
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
            'referral_source'       => 'nullable|in:instagram,tiktok,facebook,friends_family,agency,other',
            'referral_source_other' => 'nullable|string|max:255',
            'walk_video_url'        => 'nullable|url|max:500',
            'wants_model_kit'       => 'nullable|boolean',
            'model_kit_paid_at'     => 'nullable|date',
        ]);

        $userData = $request->only(['first_name', 'last_name', 'email', 'phone']);
        $userData['last_name'] = $userData['last_name'] ?? '';
        $profileData = $request->only([
            'instagram', 'age', 'gender', 'location', 'ethnicity', 'hair', 'body_type',
            'height', 'bust', 'chest', 'waist', 'hips', 'shoe_size', 'dress_size',
            'agency', 'is_agency', 'is_test_model', 'notes',
            'referral_source', 'referral_source_other', 'walk_video_url', 'wants_model_kit', 'model_kit_paid_at',
        ]);

        $oldStatus = $model->status;
        $newStatus = $request->input('status', $model->status);
        $userData['status'] = $newStatus;
        $this->modelService->updateModel($model, $userData, $profileData);

        // Manejar cambios de estado en eventos/pases
        if ($oldStatus !== $newStatus) {
            $model->load('eventsAsModelWithCasting');

            // Liberar slots, cancelar pases y marcar rejected cuando se inactiva
            if ($newStatus === 'inactive') {
                foreach ($model->eventsAsModelWithCasting as $event) {
                    if ($event->pivot->casting_time) {
                        $this->modelService->removeCastingSlot($model, $event->id);
                    }
                    $event->models()->updateExistingPivot($model->id, [
                        'status' => 'rejected',
                        'casting_status' => 'rejected',
                    ]);
                    EventPass::where('user_id', $model->id)
                        ->where('event_id', $event->id)
                        ->where('status', 'active')
                        ->update(['status' => 'cancelled']);
                }
            }

            // Reactivar pases, estado en evento y casting cuando vuelve de inactivo
            if ($oldStatus === 'inactive' && in_array($newStatus, ['pending', 'applicant'])) {
                $profile = $model->modelProfile;
                $isPriorityAgency = $profile?->agency
                    && preg_match('/\b(fanny|cg|fma|fanny\'s|cgmodels)\b/i', $profile->agency);
                $isTop = $profile?->is_top ?? false;
                $startSlot = $isPriorityAgency ? 1 : ($isTop ? 2 : 3);

                foreach ($model->eventsAsModelWithCasting as $event) {
                    if ($event->pivot->status === 'rejected') {
                        $event->models()->updateExistingPivot($model->id, [
                            'status' => 'invited',
                            'casting_status' => 'scheduled',
                        ]);

                        // Reasignar casting slot según tag
                        $slotType = $event->pivot->model_tag === 'runway_merch' ? 'merch' : 'normal';
                        $this->modelService->autoAssignCastingSlot($model, $event->id, startFromPosition: $startSlot, slotType: $slotType);
                    }
                    EventPass::where('user_id', $model->id)
                        ->where('event_id', $event->id)
                        ->where('status', 'cancelled')
                        ->update(['status' => 'active']);
                }
            }

            $this->activityLog->log(
                ActivityAction::StatusChanged, $model, $request->user(),
                "Estado cambiado de {$oldStatus} a {$newStatus}",
                ['old_status' => $oldStatus, 'new_status' => $newStatus]
            );
        }

        $this->activityLog->log(
            ActivityAction::ProfileUpdated, $model, $request->user(),
            "Perfil actualizado: {$model->first_name} {$model->last_name}"
        );

        return redirect()->route('admin.models.show', $model)
            ->with('success', 'Modelo actualizada exitosamente.');
    }

    public function destroy(Request $request, User $model)
    {
        $this->authorizeModel($model);

        $modelName = "{$model->first_name} {$model->last_name}";

        try {
            $this->modelService->deleteModel($model);
        } catch (\Exception $e) {
            return redirect()->route('admin.models.index')
                ->withErrors(['delete' => 'No se pudo eliminar la modelo: ' . $e->getMessage()]);
        }

        $this->activityLog->log(
            ActivityAction::StatusChanged, null, $request->user(),
            "Modelo eliminada: {$modelName}",
            ['deleted_user' => $modelName]
        );

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

            $event = Event::find($request->event_id);
            $this->activityLog->log(
                ActivityAction::EventAssigned, $model, $request->user(),
                "Asignada a evento: {$event->name}",
                ['event_id' => $event->id, 'event_name' => $event->name]
            );

            return back()->with('success', 'Modelo asignada al evento.');
        } catch (\Exception $e) {
            return back()->withErrors(['event' => $e->getMessage()]);
        }
    }

    public function removeEvent(Request $request, User $model, Event $event)
    {
        $this->authorizeModel($model);

        try {
            $this->modelService->removeFromEvent($model, $event->id);

            $this->activityLog->log(
                ActivityAction::EventRemoved, $model, $request->user(),
                "Removida de evento: {$event->name}",
                ['event_id' => $event->id, 'event_name' => $event->name]
            );

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

            $this->activityLog->log(
                ActivityAction::PhotoUploaded, $model, $request->user(),
                "Foto comp card #{$position} subida",
                ['position' => $position]
            );

            return back()->with('success', "Foto {$position} subida correctamente.");
        } catch (\Exception $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }
    }

    public function deletePhoto(Request $request, User $model, int $position)
    {
        $this->authorizeModel($model);

        try {
            $this->modelService->deleteCompCardPhoto($model, $position);

            $this->activityLog->log(
                ActivityAction::PhotoDeleted, $model, $request->user(),
                "Foto comp card #{$position} eliminada",
                ['position' => $position]
            );

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

            $this->activityLog->log(
                ActivityAction::PhotoUploaded, $model, $request->user(),
                "Foto de perfil actualizada"
            );

            return back()->with('success', 'Foto de perfil actualizada.');
        } catch (\Exception $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }
    }

    public function deleteProfilePicture(Request $request, User $model)
    {
        $this->authorizeModel($model);

        try {
            $this->modelService->deleteProfilePicture($model);

            $this->activityLog->log(
                ActivityAction::PhotoDeleted, $model, $request->user(),
                "Foto de perfil eliminada"
            );

            return back()->with('success', 'Foto de perfil eliminada.');
        } catch (\Exception $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }
    }

    public function sendWelcomeEmail(Request $request, User $model)
    {
        $this->authorizeModel($model);

        $log = CommunicationLog::create([
            'user_id'  => $model->id,
            'sent_by'  => $request->user()->id,
            'type'     => 'email',
            'channel'  => 'welcome_email',
            'status'   => 'queued',
        ]);

        SendWelcomeEmailJob::dispatch(
            userId: $model->id,
            logId:  $log->id,
        );

        $this->activityLog->log(
            ActivityAction::EmailSent, $model, $request->user(),
            "Email de bienvenida enviado a {$model->email}"
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

    public function downloadImportTemplate()
    {
        return Excel::download(new \App\Exports\ModelsTemplateExport(), 'models_import_template.xlsx');
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
            ->where('status', 'pending')
            ->whereNull('welcome_email_sent_at')
            ->whereHas('eventsAsModelWithCasting', fn($q) => $q->whereNotNull('event_model.casting_time'))
            ->get();

        if ($pending->isEmpty()) {
            return back()->with('info', 'No hay modelos pendientes de recibir correo.');
        }

        $count = 0;
        foreach ($pending as $model) {
            $log = CommunicationLog::create([
                'user_id'  => $model->id,
                'sent_by'  => request()->user()->id,
                'type'     => 'email',
                'channel'  => 'welcome_email',
                'status'   => 'queued',
            ]);

            SendWelcomeEmailJob::dispatch(
                userId: $model->id,
                logId:  $log->id,
            );
            $count++;
        }

        return back()->with('success', "{$count} emails encolados para envío. Se procesarán en los próximos minutos.");
    }

    public function toggleTop(User $model)
    {
        $this->authorizeModel($model);

        $profile = $model->modelProfile;
        $wasTop = $profile->is_top;
        $profile->update(['is_top' => !$wasTop]);

        // Reasignar slot si la modelo tiene casting_time en algún evento
        if (in_array($model->status, ['pending', 'applicant'])) {
            $isPriorityAgency = $profile->agency
                && preg_match('/\b(fanny|cg|fma|fanny\'s|cgmodels)\b/i', $profile->agency);

            if ($isPriorityAgency) {
                $targetSlot = 1; // Agencias prioritarias siempre en slot 1
            } else {
                $targetSlot = $wasTop ? 3 : 2; // quitó top → slot 3+, puso top → slot 2
            }

            foreach ($model->eventsAsModelWithCasting as $event) {
                if ($event->pivot->casting_time) {
                    $slotType = $event->pivot->model_tag === 'runway_merch' ? 'merch' : 'normal';
                    $this->modelService->autoAssignCastingSlot($model, $event->id, startFromPosition: $targetSlot, slotType: $slotType);
                }
            }
        }

        return back()->with('success', $profile->is_top ? 'Modelo marcada como Top.' : 'Top removido.');
    }

    public function updateStatus(Request $request, User $model)
    {
        $this->authorizeModel($model);

        $request->validate(['status' => 'required|in:inactive,pending,applicant']);

        $oldStatus = $model->status;
        $model->update(['status' => $request->status]);

        // Auto-assign casting slot cuando cambia de applicant → pending
        if ($oldStatus === 'applicant' && $request->status === 'pending') {
            $profile = $model->modelProfile;
            $isTop = $profile?->is_top ?? false;
            $isPriorityAgency = $profile?->agency
                && preg_match('/\b(fanny|cg|fma|fanny\'s|cgmodels)\b/i', $profile->agency);

            $startSlot = $isPriorityAgency ? 1 : ($isTop ? 2 : 3);

            foreach ($model->eventsAsModelWithCasting as $event) {
                if ($isPriorityAgency || $isTop || !$event->pivot->casting_time) {
                    $slotType = $event->pivot->model_tag === 'runway_merch' ? 'merch' : 'normal';
                    $this->modelService->autoAssignCastingSlot($model, $event->id, startFromPosition: $startSlot, slotType: $slotType);
                }
            }
        }

        // Liberar slots, cancelar pases y marcar rejected cuando se inactiva
        if ($request->status === 'inactive') {
            foreach ($model->eventsAsModelWithCasting as $event) {
                if ($event->pivot->casting_time) {
                    $this->modelService->removeCastingSlot($model, $event->id);
                }

                // Marcar como rejected en el evento y en casting
                $event->models()->updateExistingPivot($model->id, [
                    'status' => 'rejected',
                    'casting_status' => 'rejected',
                ]);

                // Cancelar pases activos
                EventPass::where('user_id', $model->id)
                    ->where('event_id', $event->id)
                    ->where('status', 'active')
                    ->update(['status' => 'cancelled']);
            }
        }

        // Reactivar pases, estado en evento y casting cuando vuelve de inactivo
        if ($oldStatus === 'inactive' && in_array($request->status, ['pending', 'applicant'])) {
            $profile = $model->modelProfile;
            $isPriorityAgency = $profile?->agency
                && preg_match('/\b(fanny|cg|fma|fanny\'s|cgmodels)\b/i', $profile->agency);
            $isTop = $profile?->is_top ?? false;
            $startSlot = $isPriorityAgency ? 1 : ($isTop ? 2 : 3);

            foreach ($model->eventsAsModelWithCasting as $event) {
                // Restaurar como invited en el evento y scheduled en casting
                if ($event->pivot->status === 'rejected') {
                    $event->models()->updateExistingPivot($model->id, [
                        'status' => 'invited',
                        'casting_status' => 'scheduled',
                    ]);

                    // Reasignar casting slot según tag
                    $slotType = $event->pivot->model_tag === 'runway_merch' ? 'merch' : 'normal';
                    $this->modelService->autoAssignCastingSlot($model, $event->id, startFromPosition: $startSlot, slotType: $slotType);
                }

                // Reactivar pases cancelados
                EventPass::where('user_id', $model->id)
                    ->where('event_id', $event->id)
                    ->where('status', 'cancelled')
                    ->update(['status' => 'active']);
            }
        }

        $this->activityLog->log(
            ActivityAction::StatusChanged, $model, $request->user(),
            "Estado cambiado de {$oldStatus} a {$request->status}",
            ['old_status' => $oldStatus, 'new_status' => $request->status]
        );

        return back()->with('success', 'Estado actualizado.');
    }

    public function updateEventCastingStatus(Request $request, User $model, Event $event)
    {
        $this->authorizeModel($model);

        $request->validate([
            'casting_status' => 'required|in:scheduled,checked_in,selected,no_show,rejected',
        ]);

        $pivot = DB::table('event_model')
            ->where('model_id', $model->id)
            ->where('event_id', $event->id)
            ->first();

        abort_unless($pivot, 404, 'La modelo no está asignada a este evento.');

        $previousStatus  = $pivot->casting_status;
        $newStatus       = $request->casting_status;
        $castingTime     = $pivot->casting_time;

        DB::table('event_model')
            ->where('model_id', $model->id)
            ->where('event_id', $event->id)
            ->update(['casting_status' => $newStatus]);

        if ($newStatus === 'rejected' && $previousStatus !== 'rejected') {
            // Liberar el casting slot si tenía horario asignado
            if ($castingTime) {
                $castingDayId = DB::table('event_days')
                    ->where('event_id', $event->id)
                    ->where('type', 'casting')
                    ->value('id');

                if ($castingDayId) {
                    DB::table('casting_slots')
                        ->where('event_day_id', $castingDayId)
                        ->where('time', $castingTime)
                        ->where('booked', '>', 0)
                        ->decrement('booked');
                }

                DB::table('event_model')
                    ->where('model_id', $model->id)
                    ->where('event_id', $event->id)
                    ->update(['casting_time' => null]);
            }

            // Marcar como rejected en el evento
            DB::table('event_model')
                ->where('model_id', $model->id)
                ->where('event_id', $event->id)
                ->update(['status' => 'rejected']);

            // Cancelar pass solo si está activo (no tocar los ya usados)
            DB::table('event_passes')
                ->where('user_id', $model->id)
                ->where('event_id', $event->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            // Si TODOS los eventos de la modelo están rejected → user.status = rejected
            $totalEvents = DB::table('event_model')->where('model_id', $model->id)->count();
            $rejectedEvents = DB::table('event_model')->where('model_id', $model->id)->where('status', 'rejected')->count();

            if ($totalEvents > 0 && $totalEvents === $rejectedEvents && $model->status !== 'inactive') {
                $model->update(['status' => 'rejected']);
            }

        } elseif ($previousStatus === 'rejected' && $newStatus !== 'rejected') {
            // Restaurar estado en evento a invited
            DB::table('event_model')
                ->where('model_id', $model->id)
                ->where('event_id', $event->id)
                ->update(['status' => 'invited']);

            // Reasignar casting slot según tag
            $profile = $model->modelProfile;
            $isPriorityAgency = $profile?->agency
                && preg_match('/\b(fanny|cg|fma|fanny\'s|cgmodels)\b/i', $profile->agency);
            $isTop = $profile?->is_top ?? false;
            $startSlot = $isPriorityAgency ? 1 : ($isTop ? 2 : 3);
            $pivotTag = DB::table('event_model')->where('model_id', $model->id)->where('event_id', $event->id)->value('model_tag');
            $slotType = $pivotTag === 'runway_merch' ? 'merch' : 'normal';
            $this->modelService->autoAssignCastingSlot($model, $event->id, startFromPosition: $startSlot, slotType: $slotType);

            // Reactivar pass solo si fue cancelado (no tocar los usados)
            DB::table('event_passes')
                ->where('user_id', $model->id)
                ->where('event_id', $event->id)
                ->where('status', 'cancelled')
                ->update(['status' => 'active']);

            // Si user.status era rejected, restaurar a applicant (pending requiere casting asignado)
            if ($model->status === 'rejected') {
                $model->update(['status' => 'applicant']);
            }
        }

        return back()->with('success', 'Estado de casting actualizado.');
    }

    public function updateModelTag(Request $request, User $model, Event $event)
    {
        $this->authorizeModel($model);

        $request->validate([
            'model_tag' => 'nullable|in:runway_merch,runway_brand',
        ]);

        DB::table('event_model')
            ->where('model_id', $model->id)
            ->where('event_id', $event->id)
            ->update(['model_tag' => $request->model_tag ?: null]);

        $tagLabel = match ($request->model_tag) {
            'runway_merch' => 'Runway Merch',
            'runway_brand' => 'Runway Brand',
            default => 'Sin tag',
        };

        return back()->with('success', "Tag actualizado a {$tagLabel}.");
    }

    public function sendModelOnboarding(Request $request, User $model, Event $event)
    {
        $this->authorizeModel($model);

        $pivot = DB::table('event_model')
            ->where('model_id', $model->id)
            ->where('event_id', $event->id)
            ->first();

        abort_unless($pivot, 404, 'La modelo no está asignada a este evento.');
        abort_unless($pivot->model_tag, 422, 'La modelo no tiene tag asignado para este evento.');
        abort_unless($pivot->casting_time, 422, 'La modelo no tiene horario de casting asignado.');

        $log = CommunicationLog::create([
            'user_id'  => $model->id,
            'sent_by'  => $request->user()->id,
            'type'     => 'email',
            'channel'  => 'model_onboarding',
            'status'   => 'queued',
        ]);

        SendModelOnboardingJob::dispatch(
            userId:  $model->id,
            eventId: $event->id,
            tag:     $pivot->model_tag,
            logId:   $log->id,
        );

        return back()->with('success', 'Email de onboarding enviado.');
    }

    // --- Helpers ---

    private function sanitizeInstagram(Request $request): void
    {
        if ($request->filled('instagram')) {
            $ig = $request->input('instagram');
            $ig = strtok($ig, '?');
            $ig = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $ig);
            $ig = rtrim($ig, '/');
            $ig = ltrim($ig, '@');
            $request->merge(['instagram' => $ig]);
        }
    }

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
                'shopify_order_number'  => $event->pivot->shopify_order_number,
                'model_tag'             => $event->pivot->model_tag,
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
