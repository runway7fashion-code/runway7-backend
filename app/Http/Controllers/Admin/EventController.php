<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(protected EventService $eventService) {}

    public function index(Request $request): Response
    {
        $query = Event::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $events = $query->withCount(['eventDays'])
            ->with(['eventDays'])
            ->orderBy('start_date', 'desc')
            ->paginate(12)
            ->withQueryString()
            ->through(function (Event $event) {
                $totalShows    = $event->totalShows();
                $assignedSlots = $event->totalAssignedShowSlots();
                return array_merge($event->toArray(), [
                    'total_shows'        => $totalShows,
                    'assigned_designers' => $assignedSlots,
                    'days_count'         => $event->event_days_count,
                ]);
            });

        return Inertia::render('Admin/Events/Index', [
            'events'  => $events,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Events/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'city'       => 'required|string|max:255',
            'venue'      => 'nullable|string|max:255',
            'timezone'   => 'required|string',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'description'        => 'nullable|string',
            'status'             => 'required|in:draft,published',
            'model_number_start' => 'required|integer|min:1',
            'days'               => 'required|array|min:1',
            'days.*.date'  => 'required|date',
            'days.*.label' => 'required|string',
            'days.*.type'  => 'required|in:setup,casting,show_day,ceremony,other',
            'time_slots'            => 'nullable|array',
            'apply_same_schedule'   => 'boolean',
        ]);

        $event = $this->eventService->createEvent(
            $request->only(['name', 'city', 'venue', 'timezone', 'start_date', 'end_date', 'description', 'status', 'model_number_start']),
            $request->input('days', []),
            $request->input('time_slots', []),
            $request->boolean('apply_same_schedule', true)
        );

        return redirect()->route('admin.events.show', $event)
            ->with('success', 'Evento creado exitosamente.');
    }

    public function show(Event $event): Response
    {
        $event->load([
            'eventDays.shows.designers.designerProfile',
            'eventDays.shows' => fn($q) => $q->withCount('models')->orderBy('order'),
            'eventDays.castingSlots',
        ]);

        $designers = User::designers()->with('designerProfile')->get()
            ->map(fn($d) => [
                'id'         => $d->id,
                'name'       => $d->full_name,
                'brand_name' => $d->designerProfile?->brand_name,
            ]);

        return Inertia::render('Admin/Events/Show', [
            'event'     => $event,
            'designers' => $designers,
            'stats'     => [
                'days_count'            => $event->eventDays->count(),
                'total_shows'           => $event->totalShows(),
                'assigned_show_slots'   => $event->totalAssignedShowSlots(),
                'unique_designers'      => $event->assignedDesignersCount(),
                'total_models'          => $event->models()->count(),
                'casting_slots'         => $event->castingDay()?->castingSlots()->count() ?? 0,
                'casting_booked'        => $event->castingDay()?->castingSlots()->sum('booked') ?? 0,
            ],
        ]);
    }

    public function edit(Event $event): Response
    {
        $event->load(['eventDays.shows' => fn($q) => $q->withCount('designers')->orderBy('order')]);

        return Inertia::render('Admin/Events/Edit', [
            'event' => $event,
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'city'       => 'required|string|max:255',
            'venue'      => 'nullable|string|max:255',
            'timezone'   => 'required|string',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'description'        => 'nullable|string',
            'status'             => 'required|in:draft,published,active,completed,cancelled',
            'model_number_start' => 'required|integer|min:1',
            'days'               => 'required|array|min:1',
        ]);

        $this->eventService->updateEvent(
            $event,
            $request->only(['name', 'city', 'venue', 'timezone', 'start_date', 'end_date', 'description', 'status', 'model_number_start']),
            $request->input('days', [])
        );

        return redirect()->route('admin.events.show', $event)
            ->with('success', 'Evento actualizado exitosamente.');
    }

    public function duplicate(Request $request, Event $event)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $newEvent = $this->eventService->duplicateEvent($event, array_merge(
            $event->only(['city', 'venue', 'timezone', 'description']),
            $request->only(['name', 'start_date', 'end_date']),
            ['status' => 'draft']
        ));

        return redirect()->route('admin.events.show', $newEvent)
            ->with('success', 'Evento duplicado. Revisa los detalles antes de publicar.');
    }

    public function destroy(Event $event)
    {
        $event->delete();
        return redirect()->route('admin.events.index')
            ->with('success', 'Evento eliminado.');
    }
}
