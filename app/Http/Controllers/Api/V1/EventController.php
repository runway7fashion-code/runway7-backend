<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FittingAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
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
