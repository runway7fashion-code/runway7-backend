<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Show;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    /**
     * Shows asignados al modelo autenticado.
     */
    public function myShows(Request $request): JsonResponse
    {
        $user = $request->user();

        $shows = $user->shows()
            ->with([
                'eventDay.event:id,name,city,venue',
                'designers:users.id,users.first_name,users.last_name',
            ])
            ->get();

        $data = $shows->map(function ($show) use ($user) {
            $pivot = $show->pivot;
            $designer = $pivot->designer_id
                ? $show->designers->firstWhere('id', $pivot->designer_id)
                : null;

            return [
                'id' => $show->id,
                'name' => $show->name,
                'scheduled_time' => $show->formatted_time,
                'status' => $show->status,
                'event' => [
                    'id' => $show->eventDay->event->id,
                    'name' => $show->eventDay->event->name,
                ],
                'day' => [
                    'date' => $show->eventDay->date->format('Y-m-d'),
                    'label' => $show->eventDay->label,
                ],
                'assignment' => [
                    'status' => $pivot->status,
                    'walk_order' => $pivot->walk_order,
                    'confirmed_at' => $pivot->confirmed_at,
                    'designer' => $designer ? [
                        'id' => $designer->id,
                        'name' => $designer->first_name . ' ' . $designer->last_name,
                        'collection_name' => $designer->pivot->collection_name ?? null,
                    ] : null,
                ],
            ];
        });

        return response()->json(['shows' => $data]);
    }

    /**
     * Confirmar participación en un show.
     */
    public function confirm(Request $request, Show $show): JsonResponse
    {
        $user = $request->user();

        $assignment = \DB::table('show_model')
            ->where('show_id', $show->id)
            ->where('model_id', $user->id)
            ->whereIn('status', ['requested', 'reserved'])
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'No tienes una asignación pendiente en este show.'], 404);
        }

        \DB::table('show_model')
            ->where('id', $assignment->id)
            ->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'responded_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Show confirmado exitosamente.']);
    }

    /**
     * Rechazar participación en un show.
     */
    public function reject(Request $request, Show $show): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $assignment = \DB::table('show_model')
            ->where('show_id', $show->id)
            ->where('model_id', $user->id)
            ->whereIn('status', ['requested', 'reserved'])
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'No tienes una asignación pendiente en este show.'], 404);
        }

        \DB::table('show_model')
            ->where('id', $assignment->id)
            ->update([
                'status' => 'rejected',
                'rejection_reason' => $request->input('reason'),
                'responded_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Show rechazado.']);
    }
}
