<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendMaterialNotificationJob;
use App\Models\DesignerMaterial;
use App\Models\Event;
use App\Models\MaterialFile;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArtworkController extends Controller
{
    public function __construct(private GoogleDriveService $driveService) {}

    /**
     * List designers with their Artworks material status for an event.
     */
    public function index(Request $request)
    {
        $eventId = $request->input('event_id');
        $events = Event::whereNull('deleted_at')->where('status', '!=', 'cancelled')
            ->select('id', 'name')->orderBy('start_date', 'desc')->get();

        if (!$eventId && $events->isNotEmpty()) {
            $eventId = $events->first()->id;
        }

        $designers = collect();
        if ($eventId) {
            $designers = User::where('role', 'designer')
                ->whereHas('eventsAsDesigner', fn($q) => $q->where('events.id', $eventId))
                ->with(['designerProfile:id,user_id,brand_name'])
                ->select('id', 'first_name', 'last_name', 'email', 'profile_picture')
                ->get()
                ->map(function ($designer) use ($eventId) {
                    $material = DesignerMaterial::where('designer_id', $designer->id)
                        ->where('event_id', $eventId)
                        ->where('name', 'Artworks')
                        ->with('files')
                        ->first();

                    return [
                        'id'            => $designer->id,
                        'first_name'    => $designer->first_name,
                        'last_name'     => $designer->last_name,
                        'email'         => $designer->email,
                        'profile_picture' => $designer->profile_picture,
                        'brand_name'    => $designer->designerProfile?->brand_name,
                        'material'      => $material,
                        'files_count'   => $material?->files?->count() ?? 0,
                    ];
                });

            if ($request->filled('search')) {
                $s = strtolower($request->search);
                $designers = $designers->filter(fn($d) =>
                    str_contains(strtolower($d['first_name'] . ' ' . $d['last_name']), $s) ||
                    str_contains(strtolower($d['brand_name'] ?? ''), $s)
                )->values();
            }
        }

        return Inertia::render('Admin/Tickets/Artworks', [
            'designers' => $designers,
            'events'    => $events,
            'eventId'   => (int) $eventId,
            'filters'   => $request->only(['search', 'event_id']),
        ]);
    }

    /**
     * Show artworks for a specific designer.
     */
    public function show(User $designer, int $eventId)
    {
        $material = DesignerMaterial::where('designer_id', $designer->id)
            ->where('event_id', $eventId)
            ->where('name', 'Artworks')
            ->with(['files.uploader:id,first_name,last_name'])
            ->firstOrFail();

        $event = Event::find($eventId, ['id', 'name']);

        return Inertia::render('Admin/Tickets/ArtworkDetail', [
            'designer' => $designer->load('designerProfile:id,user_id,brand_name'),
            'event'    => $event,
            'material' => $material,
        ]);
    }

    /**
     * Generate upload URL for artwork file.
     */
    public function generateUploadUrl(Request $request, DesignerMaterial $material)
    {
        $request->validate([
            'file_name' => 'required|string|max:255',
            'mime_type' => 'required|string|max:100',
        ]);

        if (!$material->drive_folder_id) {
            return response()->json(['error' => 'No Drive folder configured.'], 422);
        }

        $uploadUrl = $this->driveService->generateResumableUploadUrl(
            $material->drive_folder_id,
            $request->file_name,
            $request->mime_type,
            $request->header('Origin'),
        );

        return response()->json(['upload_url' => $uploadUrl]);
    }

    /**
     * Confirm artwork file uploaded.
     */
    public function confirmUpload(Request $request, DesignerMaterial $material)
    {
        $request->validate([
            'drive_file_id' => 'required|string|max:100',
            'file_name'     => 'required|string|max:255',
            'file_type'     => 'nullable|string|max:50',
            'mime_type'     => 'nullable|string|max:100',
            'file_size'     => 'nullable|integer',
        ]);

        $driveFile = $this->driveService->getFile($request->drive_file_id);

        MaterialFile::create([
            'material_id'   => $material->id,
            'uploaded_by'   => auth()->id(),
            'drive_file_id' => $request->drive_file_id,
            'drive_url'     => $driveFile['view_url'] ?? null,
            'file_name'     => $request->file_name,
            'file_type'     => $request->file_type,
            'mime_type'     => $request->mime_type ?? $driveFile['mime_type'] ?? null,
            'file_size'     => $request->file_size ?? $driveFile['size'] ?? null,
        ]);

        if ($material->status === 'pending') {
            $material->update(['status' => 'completed']);
        }

        // Notify designer
        SendMaterialNotificationJob::dispatch(
            recipientId: $material->designer_id,
            title: 'New Artworks Available',
            body: 'New artwork files have been uploaded for your use. Check your materials.',
            materialId: $material->id,
            senderId: auth()->id(),
        );

        return back()->with('success', 'Artwork uploaded.');
    }

    /**
     * Delete artwork file.
     */
    public function deleteFile(MaterialFile $file)
    {
        if ($file->drive_file_id) {
            try {
                $this->driveService->deleteFile($file->drive_file_id);
            } catch (\Throwable $e) {
                \Log::warning("Failed to delete Drive file: " . $e->getMessage());
            }
        }

        $file->delete();

        return back()->with('success', 'File deleted.');
    }
}
