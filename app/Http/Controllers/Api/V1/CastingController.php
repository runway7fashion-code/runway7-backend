<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\CastingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CastingController extends Controller
{
    public function __construct(protected CastingService $castingService) {}

    public function myCasting(Request $request): JsonResponse
    {
        $user = $request->user();

        $events = $user->eventsAsModel()
            ->whereNotNull('event_model.casting_time')
            ->with('eventDays')
            ->get();

        $castings = $events->map(fn($event) => [
            'event_id'      => $event->id,
            'event_name'    => $event->name,
            'casting_time'  => $event->pivot->casting_time,
            'casting_status' => $event->pivot->casting_status,
            'status'        => $event->pivot->status,
            'casting_date'  => $event->eventDays->firstWhere('type', 'casting')?->date?->format('Y-m-d'),
        ]);

        return response()->json(['castings' => $castings]);
    }

    public function confirm(Request $request, Event $event): JsonResponse
    {
        try {
            $this->castingService->confirmCastingSlot($event, $request->user());
            return response()->json(['message' => 'Horario de casting confirmado.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, Event $event): JsonResponse
    {
        try {
            $this->castingService->rejectCastingSlot($event, $request->user());
            return response()->json(['message' => 'Horario de casting rechazado. Se te asignará un nuevo horario.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
