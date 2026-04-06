<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DesignerAssistant;
use App\Models\Event;
use App\Services\DesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesignerAssistantController extends Controller
{
    public function __construct(protected DesignerService $designerService) {}

    /**
     * List designer's assistants for an event.
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $pivot = DB::table('event_designer')
            ->where('event_id', $event->id)
            ->where('designer_id', $user->id)
            ->first();

        if (!$pivot) {
            return response()->json(['message' => 'You are not assigned to this event.'], 403);
        }

        $assistants = DesignerAssistant::where('designer_id', $user->id)
            ->where('event_id', $event->id)
            ->orderBy('created_at')
            ->get(['id', 'first_name', 'last_name', 'document_id', 'phone', 'email', 'status', 'checked_in_at']);

        return response()->json([
            'data' => $assistants,
            'max_allowed' => $pivot->assistants,
            'registered' => $assistants->count(),
            'remaining' => max(0, $pivot->assistants - $assistants->count()),
        ]);
    }

    /**
     * Add a new assistant.
     * Uses DesignerService which creates the user account + EventPass with QR.
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $pivot = DB::table('event_designer')
            ->where('event_id', $event->id)
            ->where('designer_id', $user->id)
            ->first();

        if (!$pivot) {
            return response()->json(['message' => 'You are not assigned to this event.'], 403);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'document_id' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        try {
            $assistant = $this->designerService->addAssistant(
                $user,
                $event->id,
                $request->only(['first_name', 'last_name', 'document_id', 'phone', 'email']),
                $user->id,
            );

            return response()->json([
                'data' => $assistant->only(['id', 'first_name', 'last_name', 'document_id', 'phone', 'email', 'status']),
                'message' => 'Assistant added.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update an assistant.
     */
    public function update(Request $request, Event $event, DesignerAssistant $assistant): JsonResponse
    {
        $user = $request->user();

        if ($assistant->designer_id !== $user->id || $assistant->event_id !== $event->id) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'document_id' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $assistant->update($request->only(['first_name', 'last_name', 'document_id', 'phone', 'email']));

        // Sync pass holder name if user account exists
        if ($assistant->user_id) {
            \App\Models\EventPass::where('user_id', $assistant->user_id)
                ->where('event_id', $event->id)
                ->where('status', '!=', 'cancelled')
                ->update([
                    'holder_name' => $assistant->full_name,
                    'holder_email' => $request->email,
                ]);
        }

        return response()->json([
            'data' => $assistant->only(['id', 'first_name', 'last_name', 'document_id', 'phone', 'email', 'status']),
            'message' => 'Assistant updated.',
        ]);
    }

    /**
     * Delete an assistant.
     * Uses DesignerService which cancels the EventPass before deleting.
     */
    public function destroy(Request $request, Event $event, DesignerAssistant $assistant): JsonResponse
    {
        $user = $request->user();

        if ($assistant->designer_id !== $user->id || $assistant->event_id !== $event->id) {
            return response()->json(['message' => 'Not authorized.'], 403);
        }

        if ($assistant->status === 'checked_in') {
            return response()->json(['message' => 'Cannot delete an assistant that has already checked in.'], 422);
        }

        try {
            $this->designerService->removeAssistant($assistant);
            return response()->json(['message' => 'Assistant removed.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
