<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDay;
use App\Services\EventService;
use Illuminate\Http\Request;

class EventDayController extends Controller
{
    public function __construct(protected EventService $eventService) {}

    public function store(Request $request, Event $event)
    {
        $request->validate([
            'date'        => 'required|date',
            'label'       => 'required|string|max:255',
            'type'        => 'required|in:setup,casting,show_day,ceremony,other',
            'start_time'  => 'nullable|date_format:H:i',
            'end_time'    => 'nullable|date_format:H:i',
            'description' => 'nullable|string',
        ]);

        $maxOrder = $event->eventDays()->max('order') ?? -1;

        $day = $event->eventDays()->create([
            'date'        => $request->date,
            'label'       => $request->label,
            'type'        => $request->type,
            'start_time'  => $request->start_time,
            'end_time'    => $request->end_time,
            'status'      => 'scheduled',
            'description' => $request->description,
            'order'       => $maxOrder + 1,
        ]);

        if ($day->isCasting()
            && $request->filled('casting_start')
            && $request->filled('casting_end')
            && $request->filled('casting_interval')) {
            $this->eventService->generateCastingSlots(
                $day,
                $request->casting_start,
                $request->casting_end,
                (int) $request->casting_interval,
                (int) ($request->casting_capacity ?? 50)
            );
        }

        return back()->with('success', 'Día agregado al evento.');
    }

    public function update(Request $request, Event $event, EventDay $day)
    {
        $request->validate([
            'label'       => 'required|string|max:255',
            'type'        => 'required|in:setup,casting,show_day,ceremony,other',
            'start_time'  => 'nullable|date_format:H:i',
            'end_time'    => 'nullable|date_format:H:i',
            'description' => 'nullable|string',
        ]);

        $day->update($request->only(['label', 'type', 'start_time', 'end_time', 'description']));

        return back()->with('success', 'Día actualizado.');
    }

    public function destroy(Event $event, EventDay $day)
    {
        $showIds = $day->shows()->pluck('id');
        $hasAssignedShows = \Illuminate\Support\Facades\DB::table('show_designer')
            ->whereIn('show_id', $showIds)
            ->exists();

        if ($hasAssignedShows) {
            return back()->withErrors(['day' => 'No se puede eliminar este día porque tiene shows con diseñadores asignados.']);
        }

        $day->shows()->delete();
        $day->castingSlots()->delete();
        $day->delete();

        return back()->with('success', 'Día eliminado.');
    }
}
