<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Show;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    /**
     * Shows asignados al modelo autenticado.
     */
    public function myShows(Request $request): JsonResponse
    {
        $user = $request->user();

        $shows = $user->shows()
            ->with(['eventDay.event:id,name,city,venue'])
            ->get();

        // Collect all designer IDs from pivots and load them in one query
        $designerIds = $shows->pluck('pivot.designer_id')->filter()->unique()->values();
        $designers = $designerIds->isNotEmpty()
            ? User::whereIn('id', $designerIds)
                ->with('designerProfile:id,user_id,brand_name,collection_name')
                ->get(['id', 'first_name', 'last_name', 'profile_picture'])
                ->keyBy('id')
            : collect();

        $data = $shows->map(function ($show) use ($designers) {
            $pivot = $show->pivot;
            $designer = $pivot->designer_id ? $designers->get($pivot->designer_id) : null;

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
                    'message' => $pivot->notes,
                    'requested_at' => $pivot->requested_at,
                    'designer' => $designer ? [
                        'id' => $designer->id,
                        'name' => trim($designer->first_name . ' ' . $designer->last_name),
                        'brand_name' => $designer->designerProfile?->brand_name,
                        'collection_name' => $designer->designerProfile?->collection_name,
                        'profile_picture' => $designer->profile_picture,
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

        // Create chat conversation between model and designer
        if ($assignment->designer_id) {
            $designer = User::find($assignment->designer_id);
            if ($designer) {
                $this->chatService->createConversationFromShowAcceptance($show, $user, $designer);
            }
        }

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
