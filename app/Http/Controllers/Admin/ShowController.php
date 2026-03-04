<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\FittingSlot;
use App\Models\Show;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ShowController extends Controller
{
    public function __construct(protected EventService $eventService) {}

    public function store(Request $request, Event $event, EventDay $day)
    {
        $request->validate([
            'scheduled_time' => 'required|date_format:H:i',
        ]);

        $time = $request->scheduled_time;

        if ($day->shows()->whereIn('scheduled_time', [$time, $time . ':00'])->exists()) {
            return back()->withErrors(['show' => 'Ya existe un show a esa hora en este día.']);
        }

        $day->shows()->create([
            'name'           => $day->label . ' – ' . Carbon::createFromFormat('H:i', $time)->format('g:i A'),
            'scheduled_time' => $time,
            'order'          => $day->shows()->count(),
            'status'         => 'scheduled',
        ]);

        return back()->with('success', 'Show agregado.');
    }

    public function update(Request $request, Show $show)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'scheduled_time' => 'required',
            'status'         => 'required|in:scheduled,live,completed,cancelled',
            'notes'          => 'nullable|string',
        ]);

        $show->update($request->only(['name', 'scheduled_time', 'status', 'notes']));

        return back()->with('success', 'Show actualizado.');
    }

    public function destroy(Show $show)
    {
        $show->delete();
        return back()->with('success', 'Show eliminado.');
    }

    public function assignDesigner(Request $request, Show $show)
    {
        $request->validate([
            'designer_id'     => 'required|exists:users,id',
            'collection_name' => 'nullable|string|max:255',
            'fitting_slot_id' => 'nullable|exists:fitting_slots,id',
        ]);

        try {
            $updated = $this->eventService->assignDesigner(
                $show,
                $request->designer_id,
                $request->collection_name
            );

            // Asignar fitting si se seleccionó un slot
            if ($request->filled('fitting_slot_id')) {
                $fittingSlot = FittingSlot::find($request->fitting_slot_id);
                if ($fittingSlot) {
                    try {
                        $this->eventService->assignDesignerToFitting($fittingSlot, $request->designer_id);
                    } catch (\Exception $e) {
                        // Ya está asignado, ignorar
                    }
                }
            }

            $name = $updated->designers()->where('designer_id', $request->designer_id)->first()?->full_name ?? '';
            return back()->with('success', "Diseñador asignado: {$name}");
        } catch (\Exception $e) {
            return back()->withErrors(['designer' => $e->getMessage()]);
        }
    }

    public function removeDesigner(Request $request, Show $show)
    {
        $request->validate([
            'designer_id' => 'required|exists:users,id',
        ]);

        try {
            $this->eventService->removeDesigner($show, $request->designer_id);
            return back()->with('success', 'Diseñador removido del show.');
        } catch (\Exception $e) {
            return back()->withErrors(['designer' => $e->getMessage()]);
        }
    }

    public function generateShows(Request $request, Event $event)
    {
        $request->validate([
            'time_slots'   => 'required|array|min:1',
            'time_slots.*' => 'required|date_format:H:i',
        ]);

        $count = $this->eventService->generateShows($event, $request->time_slots);

        return back()->with('success', "{$count} shows generados exitosamente.");
    }
}
