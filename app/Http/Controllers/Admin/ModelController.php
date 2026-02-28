<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\ModelService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'ilike', "%{$request->search}%")
                  ->orWhere('last_name', 'ilike', "%{$request->search}%")
                  ->orWhere('email', 'ilike', "%{$request->search}%")
                  ->orWhere('phone', 'ilike', "%{$request->search}%");
            });
        }

        $models = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $events = Event::orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Models/Index', [
            'models'  => $models,
            'events'  => $events,
            'filters' => $request->only(['event', 'compcard', 'gender', 'search']),
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

        $this->modelService->updateModel($model, $userData, $profileData);

        return redirect()->route('admin.models.show', $model)
            ->with('success', 'Modelo actualizada exitosamente.');
    }

    public function destroy(User $model)
    {
        $this->authorizeModel($model);
        $model->delete();
        return redirect()->route('admin.models.index')
            ->with('success', 'Modelo eliminada.');
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
        $this->modelService->sendWelcomeEmail($model);
        return back()->with('success', 'Email de bienvenida enviado (pendiente de integración con Mailgun).');
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
        ]);

        return $data;
    }
}
