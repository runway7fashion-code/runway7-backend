<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FittingAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Listar eventos del usuario autenticado según su rol.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;

        $query = Event::query()
            ->whereIn('status', ['published', 'active'])
            ->orderBy('start_date', 'desc');

        if ($role === 'model') {
            $query->whereHas('models', fn ($q) => $q->where('users.id', $user->id));
        } elseif ($role === 'designer') {
            $query->whereHas('designers', fn ($q) => $q->where('users.id', $user->id));
        } elseif (in_array($role, ['staff', 'admin'])) {
            $query->whereHas('staff', fn ($q) => $q->where('users.id', $user->id));
        } else {
            // Otros roles: devolver eventos publicados/activos sin filtro
        }

        $events = $query->get(['id', 'name', 'slug', 'city', 'venue', 'start_date', 'end_date', 'status', 'description']);

        // For designers, include pivot data from event_designer + casting day info
        if ($role === 'designer') {
            $pivotData = \DB::table('event_designer')
                ->where('designer_id', $user->id)
                ->whereIn('event_id', $events->pluck('id'))
                ->get()
                ->keyBy('event_id');

            $castingDays = \DB::table('event_days')
                ->whereIn('event_id', $events->pluck('id'))
                ->where('type', 'casting')
                ->orderBy('date')
                ->get()
                ->groupBy('event_id');

            $eventIds = $events->pluck('id');

            $checkedInCounts = \DB::table('event_model')
                ->join('users', 'users.id', '=', 'event_model.model_id')
                ->whereIn('event_model.event_id', $eventIds)
                ->whereIn('event_model.casting_status', ['checked_in', 'selected'])
                ->whereIn('users.status', ['pending', 'active'])
                ->groupBy('event_model.event_id')
                ->select('event_model.event_id', \DB::raw('count(*) as total'))
                ->pluck('total', 'event_model.event_id');

            $registeredCounts = \DB::table('event_model')
                ->join('users', 'users.id', '=', 'event_model.model_id')
                ->whereIn('event_model.event_id', $eventIds)
                ->whereIn('users.status', ['pending', 'active'])
                ->groupBy('event_model.event_id')
                ->select('event_model.event_id', \DB::raw('count(*) as total'))
                ->pluck('total', 'event_model.event_id');

            $events->each(function ($event) use ($pivotData, $castingDays, $checkedInCounts, $registeredCounts) {
                $pivot = $pivotData->get($event->id);
                $days = $castingDays->get($event->id, collect());

                $event->designer_info = $pivot ? [
                    'model_casting_enabled' => (bool) $pivot->model_casting_enabled,
                    'looks' => $pivot->looks,
                    'assistants' => $pivot->assistants,
                    'package_price' => $pivot->package_price,
                    'status' => $pivot->status,
                    'registered_models_count' => $registeredCounts->get($event->id, 0),
                    'available_models_count' => $checkedInCounts->get($event->id, 0),
                    'casting_days' => $days->map(fn($d) => [
                        'date' => $d->date,
                        'label' => $d->label,
                        'start_time' => $d->start_time,
                        'end_time' => $d->end_time,
                    ])->values(),
                ] : null;
            });
        }

        return response()->json(['events' => $events]);
    }

    /**
     * Detalle de un evento con días y shows.
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $event->load([
            'eventDays' => fn ($q) => $q->orderBy('date')->orderBy('order'),
            'eventDays.shows' => fn ($q) => $q->orderBy('scheduled_time'),
            'eventDays.shows.designers' => fn ($q) => $q->select('users.id', 'users.first_name', 'users.last_name'),
        ]);

        $days = $event->eventDays->map(function ($day) {
            return [
                'id' => $day->id,
                'date' => $day->date->format('Y-m-d'),
                'label' => $day->label,
                'type' => $day->type,
                'start_time' => $day->start_time,
                'end_time' => $day->end_time,
                'description' => $day->description,
                'shows' => $day->shows->map(function ($show) {
                    return [
                        'id' => $show->id,
                        'name' => $show->name,
                        'scheduled_time' => $show->formatted_time,
                        'status' => $show->status,
                        'model_slots' => $show->model_slots,
                        'designers' => $show->designers->map(fn ($d) => [
                            'id' => $d->id,
                            'name' => $d->first_name . ' ' . $d->last_name,
                            'collection_name' => $d->pivot->collection_name,
                        ]),
                    ];
                }),
            ];
        });

        // Include designer_info if user is a designer assigned to this event
        $designerInfo = null;
        if ($user->role === 'designer') {
            $pivot = \DB::table('event_designer')
                ->where('event_id', $event->id)
                ->where('designer_id', $user->id)
                ->first();

            if ($pivot) {
                $castingDays = $event->eventDays->where('type', 'casting')->values();

                $designerInfo = [
                    'model_casting_enabled' => (bool) $pivot->model_casting_enabled,
                    'looks' => $pivot->looks,
                    'assistants' => $pivot->assistants,
                    'package_price' => $pivot->package_price,
                    'status' => $pivot->status,
                    'casting_days' => $castingDays->map(fn($d) => [
                        'date' => $d->date->format('Y-m-d'),
                        'label' => $d->label,
                        'start_time' => $d->start_time,
                        'end_time' => $d->end_time,
                    ])->values(),
                ];
            }
        }

        return response()->json([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'city' => $event->city,
                'venue' => $event->venue,
                'venue_address' => $event->venue_address,
                'venue_latitude' => $event->venue_latitude ? (float) $event->venue_latitude : null,
                'venue_longitude' => $event->venue_longitude ? (float) $event->venue_longitude : null,
                'start_date' => $event->start_date->format('Y-m-d'),
                'end_date' => $event->end_date->format('Y-m-d'),
                'status' => $event->status,
                'description' => $event->description,
                'call_time' => $event->call_time,
                'hmua_address' => $event->hmua_address,
                'designer_info' => $designerInfo,
                'days' => $days,
            ],
        ]);
    }
    /**
     * Obtener el schedule de fitting de la modelo autenticada.
     * La modelo hereda el fitting de su diseñador asignado.
     */
    public function myFittings(Request $request): JsonResponse
    {
        $user = $request->user();

        // Obtener eventos donde la modelo está asignada y tiene un diseñador
        $events = $user->eventsAsModel()
            ->with(['eventDays.fittingSlots.assignments.designer.designerProfile'])
            ->get();

        $fittings = [];

        foreach ($events as $event) {
            // Obtener los designer_ids que seleccionaron a esta modelo (desde show_model)
            $designerIds = \DB::table('show_model')
                ->join('shows', 'shows.id', '=', 'show_model.show_id')
                ->join('event_days', 'event_days.id', '=', 'shows.event_day_id')
                ->where('event_days.event_id', $event->id)
                ->where('show_model.model_id', $user->id)
                ->whereIn('show_model.status', ['confirmed', 'reserved'])
                ->pluck('show_model.designer_id')
                ->unique()
                ->filter()
                ->values();

            if ($designerIds->isEmpty()) continue;

            // Buscar fitting assignments de esos diseñadores en este evento
            foreach ($event->eventDays as $day) {
                if (!$day->fittingSlots || $day->fittingSlots->isEmpty()) continue;

                foreach ($day->fittingSlots as $slot) {
                    foreach ($slot->assignments as $assignment) {
                        if ($designerIds->contains($assignment->designer_id)) {
                            $fittings[] = [
                                'event_name'    => $event->name,
                                'day_label'     => $day->label,
                                'day_date'      => $day->date->format('Y-m-d'),
                                'time'          => $slot->time,
                                'designer_name' => $assignment->designer->full_name,
                                'brand_name'    => $assignment->designer->designerProfile?->brand_name,
                            ];
                        }
                    }
                }
            }
        }

        return response()->json(['fittings' => $fittings]);
    }
}
