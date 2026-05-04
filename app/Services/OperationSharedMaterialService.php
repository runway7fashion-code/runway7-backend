<?php

namespace App\Services;

use App\Jobs\SendMaterialNotificationJob;
use App\Models\DesignerMaterial;
use App\Models\Event;
use App\Models\EventSharedMaterial;
use App\Models\MaterialFile;
use App\Models\MaterialMoodboardItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OperationSharedMaterialService
{
    public function __construct(private GoogleDriveService $driveService) {}

    public const MATERIAL_RUNWAY_LOGO   = 'Runway Logo';
    public const MATERIAL_HAIR_MOODBOARD   = 'Hair Mood Board';
    public const MATERIAL_MAKEUP_MOODBOARD = 'Makeup Mood Board';

    public const MATERIALS = [
        self::MATERIAL_RUNWAY_LOGO,
        self::MATERIAL_HAIR_MOODBOARD,
        self::MATERIAL_MAKEUP_MOODBOARD,
    ];

    /**
     * Generate a resumable upload URL targeting the per-event shared folder for this material.
     */
    public function generateUploadUrl(Event $event, string $materialName, string $fileName, string $mimeType, ?string $origin = null): string
    {
        $folderId = $this->folderIdFor($event, $materialName);

        return $this->driveService->generateResumableUploadUrl($folderId, $fileName, $mimeType, $origin);
    }

    /**
     * Register an uploaded file as a global event material and replicate it to every designer.
     * Throws on duplicate drive_file_id within (event, material).
     */
    public function registerUpload(Event $event, string $materialName, array $data, int $uploadedBy): EventSharedMaterial
    {
        $this->assertValidMaterial($materialName);

        if (EventSharedMaterial::where('event_id', $event->id)
            ->where('material_name', $materialName)
            ->where('drive_file_id', $data['drive_file_id'])
            ->exists()) {
            throw new \RuntimeException('This file is already registered for this event and material.');
        }

        $driveFile = $this->driveService->getFile($data['drive_file_id']);

        return DB::transaction(function () use ($event, $materialName, $data, $uploadedBy, $driveFile) {
            $shared = EventSharedMaterial::create([
                'event_id'      => $event->id,
                'material_name' => $materialName,
                'drive_file_id' => $data['drive_file_id'],
                'drive_url'     => $driveFile['view_url'] ?? null,
                'file_name'     => $data['file_name'],
                'mime_type'     => $data['mime_type'] ?? $driveFile['mime_type'] ?? null,
                'file_size'     => $data['file_size'] ?? $driveFile['size'] ?? null,
                'uploaded_by'   => $uploadedBy,
            ]);

            $this->replicateToDesigners($shared);

            return $shared;
        });
    }

    /**
     * Delete a shared material globally.
     * Returns ['deleted' => true] on success, or ['blocked' => true, 'designers' => [...names]] if any
     * designer has already responded to a moodboard item derived from this file.
     */
    public function delete(EventSharedMaterial $shared): array
    {
        if ($this->isMoodboard($shared->material_name)) {
            $designersWithResponses = MaterialMoodboardItem::where('drive_file_id', $shared->drive_file_id)
                ->whereIn('material_id', DesignerMaterial::where('event_id', $shared->event_id)
                    ->where('name', $shared->material_name)
                    ->pluck('id'))
                ->whereNotNull('responded_at')
                ->with('material.designer')
                ->get()
                ->map(fn ($item) => trim(($item->material->designer->first_name ?? '') . ' ' . ($item->material->designer->last_name ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($designersWithResponses)) {
                return ['blocked' => true, 'designers' => $designersWithResponses];
            }
        }

        DB::transaction(function () use ($shared) {
            $materialIds = DesignerMaterial::where('event_id', $shared->event_id)
                ->where('name', $shared->material_name)
                ->pluck('id');

            if ($this->isMoodboard($shared->material_name)) {
                MaterialMoodboardItem::where('drive_file_id', $shared->drive_file_id)
                    ->whereIn('material_id', $materialIds)
                    ->delete();
            } else {
                MaterialFile::where('drive_file_id', $shared->drive_file_id)
                    ->whereIn('material_id', $materialIds)
                    ->delete();
            }

            // Recompute statuses for affected designer materials.
            DesignerMaterial::whereIn('id', $materialIds)
                ->where('status', DesignerMaterial::STATUS_COMPLETED)
                ->each(function (DesignerMaterial $m) {
                    if ($this->isMoodboard($m->name)) {
                        if ($m->moodboardItems()->count() === 0) {
                            $m->update(['status' => DesignerMaterial::STATUS_PENDING]);
                        }
                    } else {
                        if ($m->files()->count() === 0) {
                            $m->update(['status' => DesignerMaterial::STATUS_PENDING]);
                        }
                    }
                });

            try {
                $this->driveService->deleteFile($shared->drive_file_id);
            } catch (\Throwable $e) {
                \Log::warning("Failed to trash shared Drive file {$shared->drive_file_id}: " . $e->getMessage());
            }

            $shared->delete();
        });

        return ['deleted' => true];
    }

    /**
     * When a designer is added to an event, replicate any pre-existing shared materials to them.
     * Called from DesignerService::createDefaultMaterials() after the designer's 10 materials are created.
     */
    public function replicateExistingToDesigner(User $designer, Event $event): void
    {
        $shared = EventSharedMaterial::where('event_id', $event->id)->get();
        if ($shared->isEmpty()) return;

        foreach ($shared as $s) {
            $material = DesignerMaterial::where('designer_id', $designer->id)
                ->where('event_id', $event->id)
                ->where('name', $s->material_name)
                ->first();
            if (!$material) continue;

            $this->attachToDesignerMaterial($material, $s);
        }
    }

    /**
     * Replicate a single shared material to every designer of the event.
     */
    private function replicateToDesigners(EventSharedMaterial $shared): void
    {
        $materials = DesignerMaterial::where('event_id', $shared->event_id)
            ->where('name', $shared->material_name)
            ->get();

        foreach ($materials as $material) {
            $this->attachToDesignerMaterial($material, $shared);
        }
    }

    private function attachToDesignerMaterial(DesignerMaterial $material, EventSharedMaterial $shared): void
    {
        if ($this->isMoodboard($shared->material_name)) {
            $exists = MaterialMoodboardItem::where('material_id', $material->id)
                ->where('drive_file_id', $shared->drive_file_id)
                ->exists();
            if ($exists) return;

            MaterialMoodboardItem::create([
                'material_id'   => $material->id,
                'uploaded_by'   => $shared->uploaded_by,
                'drive_file_id' => $shared->drive_file_id,
                'drive_url'     => $shared->drive_url,
                'image_name'    => $shared->file_name,
                'order'         => (int) ($material->moodboardItems()->max('order') ?? 0) + 1,
            ]);

            // Adding a new moodboard item invalidates a previously-completed material.
            if ($material->status === DesignerMaterial::STATUS_COMPLETED) {
                $material->update(['status' => DesignerMaterial::STATUS_IN_PROGRESS]);
            }
        } else {
            $exists = MaterialFile::where('material_id', $material->id)
                ->where('drive_file_id', $shared->drive_file_id)
                ->exists();
            if ($exists) return;

            MaterialFile::create([
                'material_id'   => $material->id,
                'uploaded_by'   => $shared->uploaded_by,
                'drive_file_id' => $shared->drive_file_id,
                'drive_url'     => $shared->drive_url,
                'file_name'     => $shared->file_name,
                'file_type'     => 'image',
                'mime_type'     => $shared->mime_type,
                'file_size'     => $shared->file_size,
            ]);

            if ($material->status === DesignerMaterial::STATUS_PENDING) {
                $material->update(['status' => DesignerMaterial::STATUS_COMPLETED]);
            }
        }

        SendMaterialNotificationJob::dispatch(
            recipientId: $material->designer_id,
            title: "{$material->name} Updated",
            body: "Operations uploaded a new {$shared->material_name} for the event.",
            materialId: $material->id,
            senderId: $shared->uploaded_by,
        );
    }

    private function folderIdFor(Event $event, string $materialName): string
    {
        $this->assertValidMaterial($materialName);

        $column = match ($materialName) {
            self::MATERIAL_RUNWAY_LOGO      => 'shared_runway_logo_folder_id',
            self::MATERIAL_HAIR_MOODBOARD   => 'shared_hair_moodboard_folder_id',
            self::MATERIAL_MAKEUP_MOODBOARD => 'shared_makeup_moodboard_folder_id',
        };

        $folderId = $event->{$column};
        if (!$folderId) {
            throw new \RuntimeException("Drive folder not configured for {$event->name} / {$materialName}. Configure {$column} on the event first.");
        }

        return $folderId;
    }

    private function assertValidMaterial(string $materialName): void
    {
        if (!in_array($materialName, self::MATERIALS, true)) {
            throw new \InvalidArgumentException("Invalid shared material name: {$materialName}");
        }
    }

    private function isMoodboard(string $materialName): bool
    {
        return in_array($materialName, [self::MATERIAL_HAIR_MOODBOARD, self::MATERIAL_MAKEUP_MOODBOARD], true);
    }
}
