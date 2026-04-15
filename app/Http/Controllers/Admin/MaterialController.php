<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\DesignerMaterial;
use App\Models\MaterialBioContent;
use App\Models\MaterialFile;
use App\Models\MaterialMoodboardItem;
use App\Models\User;
use App\Services\ChatService;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MaterialController extends Controller
{
    public function __construct(
        private GoogleDriveService $driveService,
        private ChatService $chatService,
    ) {}

    /**
     * Show materials page for a designer in an event.
     */
    public function show(User $designer, int $eventId)
    {
        $pivot = DB::table('event_designer')
            ->where('designer_id', $designer->id)
            ->where('event_id', $eventId)
            ->first();

        if (!$pivot) {
            abort(404, 'Designer is not assigned to this event.');
        }

        $materials = DesignerMaterial::where('designer_id', $designer->id)
            ->where('event_id', $eventId)
            ->with(['files.uploader:id,first_name,last_name', 'bioContent', 'moodboardItems.uploader:id,first_name,last_name'])
            ->orderBy('order')
            ->get();

        $event = \App\Models\Event::find($eventId, ['id', 'name']);

        return Inertia::render('Admin/Designers/Materials', [
            'designer'  => $designer->load('designerProfile:id,user_id,brand_name'),
            'event'     => $event,
            'materials' => $materials,
            'pivot'     => [
                'materials_deadline'    => $pivot->materials_deadline,
                'drive_root_folder_id'  => $pivot->drive_root_folder_id,
                'drive_root_folder_url' => $pivot->drive_root_folder_url,
            ],
        ]);
    }

    /**
     * Update material status.
     */
    public function updateStatus(Request $request, DesignerMaterial $material)
    {
        $validStatuses = $material->isCollaborative()
            ? ['pending', 'in_progress', 'completed', 'confirmed', 'observed']
            : ['pending', 'completed'];

        $request->validate([
            'status' => 'required|in:' . implode(',', $validStatuses),
        ]);

        $material->update(['status' => $request->status]);

        return back()->with('success', 'Status updated.');
    }

    /**
     * Generate a resumable upload URL for direct browser-to-Drive upload.
     */
    public function generateUploadUrl(Request $request, DesignerMaterial $material)
    {
        $request->validate([
            'file_name' => 'required|string|max:255',
            'mime_type' => 'required|string|max:100',
        ]);

        if (!$material->drive_folder_id) {
            return response()->json(['error' => 'No Drive folder configured for this material.'], 422);
        }

        $uploadUrl = $this->driveService->generateResumableUploadUrl(
            $material->drive_folder_id,
            $request->file_name,
            $request->mime_type,
        );

        return response()->json(['upload_url' => $uploadUrl]);
    }

    /**
     * Confirm that a file was uploaded to Drive.
     */
    public function confirmUpload(Request $request, DesignerMaterial $material)
    {
        $request->validate([
            'drive_file_id' => 'required|string|max:100',
            'file_name'     => 'required|string|max:255',
            'file_type'     => 'nullable|string|max:50',
            'mime_type'     => 'nullable|string|max:100',
            'file_size'     => 'nullable|integer',
            'note'          => 'nullable|string|max:1000',
        ]);

        // Get Drive URL
        $driveFile = $this->driveService->getFile($request->drive_file_id);

        $file = MaterialFile::create([
            'material_id'   => $material->id,
            'uploaded_by'   => auth()->id(),
            'drive_file_id' => $request->drive_file_id,
            'drive_url'     => $driveFile['view_url'] ?? null,
            'file_name'     => $request->file_name,
            'file_type'     => $request->file_type,
            'mime_type'     => $request->mime_type ?? $driveFile['mime_type'] ?? null,
            'file_size'     => $request->file_size ?? $driveFile['size'] ?? null,
            'note'          => $request->note,
        ]);

        // Auto-update status based on flow
        if ($material->status === 'pending') {
            $newStatus = $material->isCollaborative() ? 'in_progress' : 'completed';
            $material->update(['status' => $newStatus]);
        }

        return back()->with('success', 'File uploaded.');
    }

    /**
     * Delete a material file.
     */
    public function deleteFile(MaterialFile $file)
    {
        if ($file->drive_file_id) {
            try {
                $this->driveService->deleteFile($file->drive_file_id);
            } catch (\Throwable $e) {
                \Log::warning("Failed to delete Drive file {$file->drive_file_id}: " . $e->getMessage());
            }
        }

        $file->delete();

        return back()->with('success', 'File deleted.');
    }

    /**
     * Save bio content.
     */
    public function saveBio(Request $request, DesignerMaterial $material)
    {
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

        // Auto-complete if any field is filled
        $hasContent = collect($request->only(['biography', 'collection_description', 'additional_notes', 'contact_info']))
            ->filter(fn($v) => !empty($v))->isNotEmpty();

        if ($hasContent && $material->status === 'pending') {
            $material->update(['status' => 'completed']);
        }

        return back()->with('success', 'Bio saved.');
    }

    /**
     * Upload a moodboard image (Operation uploads, designer responds later).
     */
    public function uploadMoodboardImage(Request $request, DesignerMaterial $material)
    {
        $request->validate([
            'drive_file_id' => 'required|string|max:100',
            'image_name'    => 'required|string|max:255',
        ]);

        $driveFile = $this->driveService->getFile($request->drive_file_id);

        MaterialMoodboardItem::create([
            'material_id'   => $material->id,
            'uploaded_by'   => auth()->id(),
            'drive_file_id' => $request->drive_file_id,
            'drive_url'     => $driveFile['view_url'] ?? null,
            'image_name'    => $request->image_name,
            'order'         => $material->moodboardItems()->max('order') + 1,
        ]);

        return back()->with('success', 'Moodboard image added.');
    }

    /**
     * Designer responds to a moodboard image with text.
     */
    public function respondMoodboard(Request $request, MaterialMoodboardItem $item)
    {
        $request->validate([
            'response_text' => 'required|string|max:2000',
        ]);

        $item->update([
            'response_text' => $request->response_text,
            'responded_at'  => now(),
        ]);

        // Check if all items have responses → mark as completed
        $material = $item->material;
        $allResponded = $material->moodboardItems()->whereNull('responded_at')->count() === 0;
        if ($allResponded && $material->moodboardItems()->count() > 0) {
            $material->update(['status' => 'completed']);
        }

        return back()->with('success', 'Response saved.');
    }

    /**
     * Designer observes (rejects) a collaborative material → creates/uses chat with Operation.
     */
    public function observe(Request $request, DesignerMaterial $material)
    {
        $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $material->update(['status' => 'observed']);

        // Find an Operation user to chat with (first active operation user)
        $operationUser = User::where('role', 'operation')->where('status', 'active')->first();
        if (!$operationUser) {
            $operationUser = User::where('role', 'admin')->first();
        }

        if ($operationUser) {
            $conversation = Conversation::findOrCreateBetween($material->designer_id, $operationUser->id);
            $this->chatService->sendMessage(
                $conversation,
                User::find($material->designer_id),
                "[{$material->name}] {$request->note}",
                'text'
            );
        }

        return back()->with('success', 'Material observed. Message sent to Operations.');
    }

    /**
     * Update materials deadline for a designer/event.
     */
    public function updateDeadline(Request $request, User $designer, int $eventId)
    {
        $request->validate([
            'materials_deadline' => 'required|date',
        ]);

        DB::table('event_designer')
            ->where('designer_id', $designer->id)
            ->where('event_id', $eventId)
            ->update(['materials_deadline' => $request->materials_deadline]);

        return back()->with('success', 'Deadline updated.');
    }

    /**
     * Upload Runway Logo files for an event (visible to all designers in that event).
     */
    public function uploadRunwayLogo(Request $request, int $eventId)
    {
        $request->validate([
            'drive_file_id' => 'required|string|max:100',
            'file_name'     => 'required|string|max:255',
        ]);

        $driveFile = $this->driveService->getFile($request->drive_file_id);

        // Add to all "Runway Logo" materials for this event
        $materials = DesignerMaterial::where('event_id', $eventId)
            ->where('name', 'Runway Logo')
            ->get();

        foreach ($materials as $material) {
            MaterialFile::create([
                'material_id'   => $material->id,
                'uploaded_by'   => auth()->id(),
                'drive_file_id' => $request->drive_file_id,
                'drive_url'     => $driveFile['view_url'] ?? null,
                'file_name'     => $request->file_name,
                'file_type'     => 'image',
                'mime_type'     => $driveFile['mime_type'] ?? null,
                'file_size'     => $driveFile['size'] ?? null,
            ]);

            if ($material->status === 'pending') {
                $material->update(['status' => 'completed']);
            }
        }

        return back()->with('success', 'Runway logo uploaded for all designers in this event.');
    }
}
