<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendMaterialNotificationJob;
use App\Models\Conversation;
use App\Models\DesignerMaterial;
use App\Models\MaterialBioContent;
use App\Models\MaterialFile;
use App\Models\MaterialMoodboardItem;
use App\Models\User;
use App\Services\ChatService;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(
        private GoogleDriveService $driveService,
        private ChatService $chatService,
    ) {}

    /**
     * GET /api/v1/my-materials
     * List all materials for the authenticated designer's event(s).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'designer') {
            return response()->json(['message' => 'Only designers can access materials.'], 403);
        }

        $eventId = $request->input('event_id');

        $query = DesignerMaterial::where('designer_id', $user->id)
            ->with(['files', 'bioContent', 'moodboardItems'])
            ->orderBy('order');

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $instructionsMap = \App\Models\MaterialInstruction::map();
        $materials = $query->get()->map(fn($m) => $this->formatMaterial($m, $instructionsMap));

        // Get deadline (effective: per-designer override OR event default)
        $pivot = null;
        if ($eventId) {
            $pivot = \DB::table('event_designer as ed')
                ->join('events as e', 'e.id', '=', 'ed.event_id')
                ->where('ed.designer_id', $user->id)
                ->where('ed.event_id', $eventId)
                ->selectRaw('COALESCE(ed.materials_deadline, e.materials_deadline_default) as materials_deadline, ed.drive_root_folder_url')
                ->first();
        }

        return response()->json([
            'materials' => $materials,
            'deadline'  => $pivot?->materials_deadline,
            'drive_url' => $pivot?->drive_root_folder_url,
        ]);
    }

    /**
     * GET /api/v1/my-materials/summary
     * Aggregated progress per event for the authenticated designer's home screen.
     * One entry per event the designer participates in, with materials counts
     * + deadline so the mobile app can render the "Materials Onboarding" carousel
     * with a single request instead of N (one per event).
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'designer') {
            return response()->json(['message' => 'Only designers can access materials.'], 403);
        }

        $rows = \DB::table('event_designer as ed')
            ->join('events as e', 'e.id', '=', 'ed.event_id')
            ->where('ed.designer_id', $user->id)
            ->orderByDesc('e.start_date')
            ->selectRaw('
                ed.event_id,
                COALESCE(ed.materials_deadline, e.materials_deadline_default) as materials_deadline,
                ed.drive_root_folder_url,
                e.name as event_name,
                e.start_date
            ')
            ->get();

        $eventIds = $rows->pluck('event_id')->all();

        // Counts per event in one query: total + how many are 'done'.
        // 'done' = status in ('completed', 'confirmed') for both flows. 'observed'
        // means it needs rework so it does NOT count as completed.
        $counts = \DB::table('designer_materials')
            ->where('designer_id', $user->id)
            ->whereIn('event_id', $eventIds)
            ->selectRaw('event_id,
                count(*) as total,
                count(*) filter (where status in (?, ?)) as completed',
                [DesignerMaterial::STATUS_COMPLETED, DesignerMaterial::STATUS_CONFIRMED])
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $data = $rows->map(function ($r) use ($counts) {
            $c = $counts->get($r->event_id);
            return [
                'event_id'    => (int) $r->event_id,
                'event_name'  => $r->event_name,
                'deadline'    => $r->materials_deadline,
                'drive_url'   => $r->drive_root_folder_url,
                'total'       => $c ? (int) $c->total : 0,
                'completed'   => $c ? (int) $c->completed : 0,
                'start_date'  => $r->start_date,
            ];
        });

        return response()->json(['data' => $data->values()]);
    }

    /**
     * POST /api/v1/materials/{material}/upload-url
     * Generate a resumable upload URL for direct upload to Google Drive.
     */
    public function uploadUrl(Request $request, DesignerMaterial $material): JsonResponse
    {
        $this->authorizeDesigner($request, $material);

        $request->validate([
            'file_name' => 'required|string|max:255',
            'mime_type' => 'required|string|max:100',
        ]);

        if (!$material->drive_folder_id) {
            return response()->json(['message' => 'No Drive folder configured.'], 422);
        }

        // Check deadline
        if ($this->isDeadlinePassed($material)) {
            return response()->json(['message' => 'Upload deadline has passed.'], 422);
        }

        $uploadUrl = $this->driveService->generateResumableUploadUrl(
            $material->drive_folder_id,
            $request->file_name,
            $request->mime_type,
        );

        return response()->json(['upload_url' => $uploadUrl]);
    }

    /**
     * POST /api/v1/materials/{material}/upload-complete
     * Confirm a file was uploaded to Drive.
     */
    public function uploadComplete(Request $request, DesignerMaterial $material): JsonResponse
    {
        $this->authorizeDesigner($request, $material);

        $request->validate([
            'drive_file_id' => 'required|string|max:100',
            'file_name'     => 'required|string|max:255',
            'file_type'     => 'nullable|string|max:50',
            'mime_type'     => 'nullable|string|max:100',
            'file_size'     => 'nullable|integer',
            'note'          => 'nullable|string|max:1000',
        ]);

        $driveFile = $this->driveService->getFile($request->drive_file_id);

        $file = MaterialFile::create([
            'material_id'   => $material->id,
            'uploaded_by'   => $request->user()->id,
            'drive_file_id' => $request->drive_file_id,
            'drive_url'     => $driveFile['view_url'] ?? null,
            'file_name'     => $request->file_name,
            'file_type'     => $request->file_type,
            'mime_type'     => $request->mime_type ?? $driveFile['mime_type'] ?? null,
            'file_size'     => $request->file_size ?? $driveFile['size'] ?? null,
            'note'          => $request->note,
        ]);

        // Auto-update status
        if ($material->status === 'pending') {
            $newStatus = $material->isCollaborative() ? 'in_progress' : 'completed';
            $material->update(['status' => $newStatus]);
        }

        // Notify all operation users
        $operationUsers = User::where('role', 'operation')->where('status', 'active')->get();
        $designerName = $request->user()->first_name . ' ' . $request->user()->last_name;
        foreach ($operationUsers as $opUser) {
            SendMaterialNotificationJob::dispatch(
                recipientId: $opUser->id,
                title: 'Material Uploaded',
                body: "{$designerName} uploaded a file to {$material->name}.",
                materialId: $material->id,
                senderId: $request->user()->id,
            );
        }

        return response()->json([
            'message' => 'File uploaded.',
            'file'    => $file,
        ], 201);
    }

    /**
     * POST /api/v1/materials/{material}/confirm
     * Designer confirms a collaborative material.
     */
    public function confirm(Request $request, DesignerMaterial $material): JsonResponse
    {
        $this->authorizeDesigner($request, $material);

        if (!$material->isCollaborative()) {
            return response()->json(['message' => 'This material does not require confirmation.'], 422);
        }

        if ($material->status !== 'completed') {
            return response()->json(['message' => 'Material must be completed before confirmation.'], 422);
        }

        $material->update(['status' => 'confirmed']);

        return response()->json(['message' => 'Material confirmed.']);
    }

    /**
     * POST /api/v1/materials/{material}/observe
     * Designer observes (rejects) a collaborative material and sends a message to Operation.
     */
    public function observe(Request $request, DesignerMaterial $material): JsonResponse
    {
        $this->authorizeDesigner($request, $material);

        $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        if (!$material->isCollaborative()) {
            return response()->json(['message' => 'This material does not support observations.'], 422);
        }

        if ($material->status !== 'completed') {
            return response()->json(['message' => 'Material must be completed before observing.'], 422);
        }

        $material->update(['status' => 'observed']);

        // Find Operation user to chat with
        $operationUser = User::where('role', 'operation')->where('status', 'active')->first()
            ?? User::where('role', 'admin')->first();

        if ($operationUser) {
            $conversation = Conversation::findOrCreateBetween($material->designer_id, $operationUser->id);
            $this->chatService->sendMessage(
                $conversation,
                $request->user(),
                "[{$material->name}] {$request->note}",
            );
        }

        return response()->json(['message' => 'Observation sent to Operations.']);
    }

    /**
     * PUT /api/v1/materials/{material}/bio
     * Save bio content.
     */
    public function saveBio(Request $request, DesignerMaterial $material): JsonResponse
    {
        $this->authorizeDesigner($request, $material);

        if (!$material->isBio()) {
            return response()->json(['message' => 'This material is not a bio.'], 422);
        }

        $request->validate([
            'biography'              => 'nullable|string|max:5000',
            'collection_description' => 'nullable|string|max:5000',
            'additional_notes'       => 'nullable|string|max:5000',
            'contact_info'           => 'nullable|string|max:2000',
        ]);

        MaterialBioContent::updateOrCreate(
            ['material_id' => $material->id],
            $request->only(['biography', 'collection_description', 'additional_notes', 'contact_info']),
        );

        $hasContent = collect($request->only(['biography', 'collection_description', 'additional_notes', 'contact_info']))
            ->filter(fn($v) => !empty($v))->isNotEmpty();

        if ($hasContent && $material->status === 'pending') {
            $material->update(['status' => 'completed']);
        }

        return response()->json(['message' => 'Bio saved.']);
    }

    /**
     * POST /api/v1/materials/{material}/moodboard-respond
     * Designer responds to a moodboard image with text.
     */
    public function moodboardRespond(Request $request, DesignerMaterial $material): JsonResponse
    {
        $this->authorizeDesigner($request, $material);

        $request->validate([
            'item_id'       => 'required|exists:material_moodboard_items,id',
            'response_text' => 'required|string|max:2000',
        ]);

        $item = MaterialMoodboardItem::where('id', $request->item_id)
            ->where('material_id', $material->id)
            ->firstOrFail();

        $item->update([
            'response_text' => $request->response_text,
            'responded_at'  => now(),
        ]);

        // Check if all items responded → mark completed
        $allResponded = $material->moodboardItems()->whereNull('responded_at')->count() === 0;
        if ($allResponded && $material->moodboardItems()->count() > 0) {
            $material->update(['status' => 'completed']);
        }

        return response()->json(['message' => 'Response saved.']);
    }

    // --- Helpers ---

    private function authorizeDesigner(Request $request, DesignerMaterial $material): void
    {
        if ($request->user()->id !== $material->designer_id) {
            abort(403, 'You do not have access to this material.');
        }
    }

    private function isDeadlinePassed(DesignerMaterial $material): bool
    {
        $deadline = \App\Models\Event::effectiveMaterialsDeadline($material->designer_id, $material->event_id);
        if (!$deadline) return false;
        return now()->startOfDay()->greaterThan($deadline);
    }

    private function formatMaterial(DesignerMaterial $m, array $instructionsMap = []): array
    {
        $data = [
            'id'               => $m->id,
            'name'             => $m->name,
            'description'      => $m->description,
            'instructions'     => $instructionsMap[$m->name] ?? null,
            'status'           => $m->status,
            'status_flow'      => $m->status_flow,
            'upload_by'        => $m->upload_by,
            'is_readonly'      => $m->is_readonly,
            'drive_folder_url' => $m->drive_folder_url,
            'order'            => $m->order,
            'files'            => $m->files->map(fn($f) => [
                'id'          => $f->id,
                'file_name'   => $f->file_name,
                'file_type'   => $f->file_type,
                'file_size'   => $f->file_size,
                'drive_url'   => $f->drive_url,
                'note'        => $f->note,
                'is_final'    => $f->is_final,
                'created_at'  => $f->created_at->toISOString(),
            ]),
        ];

        if ($m->isBio() && $m->bioContent) {
            $data['bio'] = [
                'biography'              => $m->bioContent->biography,
                'collection_description' => $m->bioContent->collection_description,
                'additional_notes'       => $m->bioContent->additional_notes,
                'contact_info'           => $m->bioContent->contact_info,
            ];
        }

        if ($m->isMoodboard()) {
            $data['moodboard_items'] = $m->moodboardItems->map(fn($i) => [
                'id'            => $i->id,
                'image_name'    => $i->image_name,
                'drive_url'     => $i->drive_url,
                'response_text' => $i->response_text,
                'responded_at'  => $i->responded_at?->toISOString(),
            ]);
        }

        return $data;
    }
}
