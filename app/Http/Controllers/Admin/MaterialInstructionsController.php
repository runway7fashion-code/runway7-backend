<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignerMaterial;
use App\Models\Event;
use App\Models\EventSharedMaterial;
use App\Models\MaterialInstruction;
use App\Services\OperationSharedMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaterialInstructionsController extends Controller
{
    public function __construct(private OperationSharedMaterialService $sharedService) {}

    public function index(): Response
    {
        // Sort by the canonical order defined in DesignerMaterial::MATERIALS
        $order = array_keys(DesignerMaterial::MATERIALS);
        $instructions = MaterialInstruction::all()
            ->sortBy(fn ($row) => array_search($row->material_name, $order, true))
            ->values()
            ->map(function ($row) {
                $meta = DesignerMaterial::MATERIALS[$row->material_name] ?? [];
                return [
                    'id'             => $row->id,
                    'material_name'  => $row->material_name,
                    'instructions'   => $row->instructions,
                    'upload_by'      => $meta['upload_by'] ?? null,
                    'flow'           => $meta['flow'] ?? null,
                    'updated_at'     => $row->updated_at?->toIso8601String(),
                ];
            });

        // Events that operations can manage default deadlines for
        $eventModels = Event::whereIn('status', ['published', 'active', 'draft'])
            ->orderBy('start_date', 'desc')
            ->get();

        $sharedByEvent = EventSharedMaterial::whereIn('event_id', $eventModels->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(['event_id', 'material_name']);

        $events = $eventModels->map(function ($e) use ($sharedByEvent) {
            $designerCount = DB::table('event_designer')->where('event_id', $e->id)->count();
            $withCustom = DB::table('event_designer')
                ->where('event_id', $e->id)
                ->whereNotNull('materials_deadline')
                ->count();

            $byMaterial = $sharedByEvent[$e->id] ?? collect();
            $shared = collect(OperationSharedMaterialService::MATERIALS)->mapWithKeys(function ($name) use ($byMaterial, $e) {
                $files = ($byMaterial[$name] ?? collect())->map(fn ($f) => [
                    'id'         => $f->id,
                    'file_name'  => $f->file_name,
                    'mime_type'  => $f->mime_type,
                    'file_size'  => $f->file_size,
                    'drive_url'  => $f->drive_url,
                    'created_at' => $f->created_at?->toIso8601String(),
                ])->values();

                return [$name => [
                    'configured' => (bool) match ($name) {
                        OperationSharedMaterialService::MATERIAL_RUNWAY_LOGO      => $e->shared_runway_logo_folder_id,
                        OperationSharedMaterialService::MATERIAL_HAIR_MOODBOARD   => $e->shared_hair_moodboard_folder_id,
                        OperationSharedMaterialService::MATERIAL_MAKEUP_MOODBOARD => $e->shared_makeup_moodboard_folder_id,
                    },
                    'files' => $files,
                ]];
            });

            return [
                'id'                          => $e->id,
                'name'                        => $e->name,
                'start_date'                  => $e->start_date?->toDateString(),
                'materials_deadline_default'  => $e->materials_deadline_default?->toDateString(),
                'designers_count'             => $designerCount,
                'designers_with_custom'       => $withCustom,
                'shared'                      => $shared,
            ];
        });

        return Inertia::render('Admin/Designers/MaterialInstructions', [
            'instructions'    => $instructions,
            'events'          => $events,
            'sharedMaterials' => array_values(OperationSharedMaterialService::MATERIALS),
        ]);
    }

    /**
     * POST /admin/operations/designers/material-instructions/events/{event}/shared/upload-url
     * Generate a resumable upload URL for a shared material file.
     */
    public function sharedUploadUrl(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'material_name' => 'required|string|in:' . implode(',', OperationSharedMaterialService::MATERIALS),
            'file_name'     => 'required|string|max:255',
            'mime_type'     => 'required|string|max:100',
        ]);

        try {
            $url = $this->sharedService->generateUploadUrl(
                $event,
                $data['material_name'],
                $data['file_name'],
                $data['mime_type'],
                $request->header('Origin'),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['upload_url' => $url]);
    }

    /**
     * POST /admin/operations/designers/material-instructions/events/{event}/shared/upload-complete
     * Register an uploaded file as a global shared material and replicate to all designers.
     */
    public function sharedUploadComplete(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'material_name' => 'required|string|in:' . implode(',', OperationSharedMaterialService::MATERIALS),
            'drive_file_id' => 'required|string|max:100',
            'file_name'     => 'required|string|max:255',
            'mime_type'     => 'nullable|string|max:100',
            'file_size'     => 'nullable|integer',
        ]);

        try {
            $shared = $this->sharedService->registerUpload(
                $event,
                $data['material_name'],
                [
                    'drive_file_id' => $data['drive_file_id'],
                    'file_name'     => $data['file_name'],
                    'mime_type'     => $data['mime_type'] ?? null,
                    'file_size'     => $data['file_size'] ?? null,
                ],
                auth()->id(),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Shared material uploaded and replicated to all designers.',
            'shared'  => $shared,
        ], 201);
    }

    /**
     * DELETE /admin/operations/designers/material-instructions/shared/{eventSharedMaterial}
     */
    public function sharedDestroy(EventSharedMaterial $eventSharedMaterial): JsonResponse
    {
        $result = $this->sharedService->delete($eventSharedMaterial);

        if (!empty($result['blocked'])) {
            return response()->json([
                'message'   => 'Cannot delete: some designers have already responded to this image.',
                'designers' => $result['designers'],
            ], 422);
        }

        return response()->json(['message' => 'Shared material deleted.']);
    }

    public function update(Request $request, MaterialInstruction $instruction)
    {
        $request->validate([
            'instructions' => 'nullable|string|max:5000',
        ]);

        $instruction->update([
            'instructions' => $request->instructions,
        ]);

        return back()->with('success', "Instructions for {$instruction->material_name} updated.");
    }

    /**
     * Update the default materials deadline for an event.
     * - 'overwrite' = false (default): only fills the deadline of designers that DON'T have a custom one (NULL).
     * - 'overwrite' = true: forces this deadline on every designer of the event (replacing custom values).
     */
    public function updateEventDeadline(Request $request, Event $event)
    {
        $data = $request->validate([
            'materials_deadline_default' => 'nullable|date',
            'overwrite'                  => 'boolean',
        ]);

        $deadline  = $data['materials_deadline_default'] ?? null;
        $overwrite = (bool) ($data['overwrite'] ?? false);

        DB::transaction(function () use ($event, $deadline, $overwrite) {
            $event->update(['materials_deadline_default' => $deadline]);

            if ($deadline === null) {
                // Clearing the default: don't touch per-designer values, the app will just have no fallback.
                return;
            }

            $query = DB::table('event_designer')->where('event_id', $event->id);
            if (!$overwrite) {
                $query->whereNull('materials_deadline');
            }
            $query->update(['materials_deadline' => $deadline]);
        });

        $msg = $deadline === null
            ? "Default deadline cleared for {$event->name}."
            : ($overwrite
                ? "Default deadline applied to ALL designers of {$event->name}."
                : "Default deadline applied to designers without a custom one in {$event->name}.");

        return back()->with('success', $msg);
    }
}
