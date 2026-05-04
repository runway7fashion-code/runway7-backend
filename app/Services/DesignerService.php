<?php

namespace App\Services;

use App\Models\DesignerAssistant;
use App\Services\GoogleDriveService;
use App\Models\DesignerDisplay;
use App\Models\DesignerMaterial;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventPass;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DesignerService
{
    /**
     * Crear un diseñador completo: usuario + perfil + asignación opcional a evento.
     */
    public function createDesigner(array $userData, array $profileData, ?int $eventId = null, ?array $eventData = null): User
    {
        return DB::transaction(function () use ($userData, $profileData, $eventId, $eventData) {
            $user = User::create([
                'first_name' => $userData['first_name'],
                'last_name'  => $userData['last_name'],
                'email'      => $userData['email'],
                'phone'      => $userData['phone'] ?? null,
                'password'   => bcrypt('runway7'),
                'role'       => 'designer',
                'status'     => 'pending',
            ]);

            $user->designerProfile()->create($profileData);

            if ($eventId && $eventData) {
                $this->assignToEvent($user, $eventId, $eventData);
            }

            return $user->load('designerProfile');
        });
    }

    /**
     * Actualizar datos de un diseñador.
     */
    public function updateDesigner(User $user, array $userData, array $profileData): User
    {
        return DB::transaction(function () use ($user, $userData, $profileData) {
            $user->update([
                'first_name' => $userData['first_name'] ?? $user->first_name,
                'last_name'  => $userData['last_name'] ?? $user->last_name,
                'email'      => $userData['email'] ?? $user->email,
                'phone'      => $userData['phone'] ?? $user->phone,
                'status'     => $userData['status'] ?? $user->status,
            ]);

            $user->designerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            return $user->fresh('designerProfile');
        });
    }

    /**
     * Asignar diseñador a un evento.
     */
    public function assignToEvent(User $user, int $eventId, array $data): void
    {
        $event = Event::findOrFail($eventId);

        if ($event->designers()->where('designer_id', $user->id)->exists()) {
            throw new \Exception('El diseñador ya está asignado a este evento.');
        }

        $event->designers()->attach($user->id, [
            'status'                => 'confirmed',
            'package_id'            => $data['package_id'] ?? null,
            'looks'                 => $data['looks'] ?? 10,
            'assistants'            => $data['assistants'] ?? 1,
            'model_casting_enabled' => $data['model_casting_enabled'] ?? true,
            'media_package'         => $data['media_package'] ?? false,
            'custom_background'     => $data['custom_background'] ?? false,
            'courtesy_tickets'      => $data['courtesy_tickets'] ?? false,
            'package_price'         => $data['package_price'] ?? 0,
            'notes'                 => $data['notes'] ?? null,
        ]);

        $this->createDefaultMaterials($user, $event);

        DesignerDisplay::create([
            'designer_id' => $user->id,
            'event_id'    => $event->id,
            'status'      => 'pending',
        ]);
    }

    /**
     * Create default materials for a designer in an event.
     * Also creates Google Drive folders for each material.
     */
    public function createDefaultMaterials(User $user, Event $event): void
    {
        $brandName = $user->designerProfile?->brand_name ?? "{$user->first_name} {$user->last_name}";
        $driveFolders = null;

        // Create Google Drive folders
        try {
            $driveService = app(GoogleDriveService::class);
            $driveFolders = $driveService->createDesignerFolders($brandName);

            // Save root folder to event_designer pivot
            \DB::table('event_designer')
                ->where('designer_id', $user->id)
                ->where('event_id', $event->id)
                ->update([
                    'drive_root_folder_id'  => $driveFolders['root']['id'],
                    'drive_root_folder_url' => $driveFolders['root']['url'],
                ]);
        } catch (\Throwable $e) {
            \Log::warning("Failed to create Drive folders for designer {$user->id}: " . $e->getMessage());
        }

        foreach (DesignerMaterial::MATERIALS as $name => $config) {
            $driveFolder = $driveFolders['materials'][$name] ?? null;

            DesignerMaterial::create([
                'designer_id'      => $user->id,
                'event_id'         => $event->id,
                'name'             => $name,
                'description'      => $this->getMaterialDescription($name),
                'type'             => 'production_element',
                'order'            => $config['order'],
                'status'           => 'pending',
                'status_flow'      => $config['flow'],
                'upload_by'        => $config['upload_by'],
                'is_readonly'      => $config['is_readonly'] ?? false,
                'drive_folder_id'  => $driveFolder['id'] ?? null,
                'drive_folder_url' => $driveFolder['url'] ?? null,
            ]);
        }

        // Replicate any pre-existing global Operation files (Runway Logo, Moodboards) to the new designer.
        try {
            app(\App\Services\OperationSharedMaterialService::class)->replicateExistingToDesigner($user, $event);
        } catch (\Throwable $e) {
            \Log::warning("Failed to replicate shared materials for designer {$user->id} in event {$event->id}: " . $e->getMessage());
        }
    }

    private function getMaterialDescription(string $name): string
    {
        return match ($name) {
            'Background'        => 'Background video or image for the runway display',
            'Music'             => 'Music track for the runway show',
            'Images'            => 'Collection images for promotional materials',
            'Runway Logo'       => 'Runway 7 logo variants for designer use',
            'Bio'               => 'Designer biography, collection description, and contact info',
            'Hair Mood Board'   => 'Hair styling mood board for models',
            'Makeup Mood Board' => 'Makeup mood board for models',
            'Brand Logo'        => 'Official brand logo in high resolution',
            'Designer Photo'    => 'Professional photo of the designer',
            'Artworks'          => 'Event artwork files for social media and promotions',
            default             => '',
        };
    }

    /**
     * Agregar asistente a un diseñador en un evento.
     * Si se provee email, crea o recupera una cuenta de usuario con rol 'assistant'
     * y genera un pase de evento para el asistente.
     */
    public function addAssistant(User $designer, int $eventId, array $data, int $issuedById): DesignerAssistant
    {
        // Validar límite de asistentes negociado en ventas
        $eventDesigner = DB::table('event_designer')
            ->where('event_id', $eventId)
            ->where('designer_id', $designer->id)
            ->first();

        if ($eventDesigner) {
            $current = DesignerAssistant::where('designer_id', $designer->id)
                ->where('event_id', $eventId)
                ->count();

            if ($current >= $eventDesigner->assistants) {
                throw new \Exception(
                    "Este diseñador solo tiene {$eventDesigner->assistants} asistente(s) incluido(s) en su paquete."
                );
            }
        }

        return DB::transaction(function () use ($designer, $eventId, $data, $issuedById) {
            $assistantUser = null;

            if (!empty($data['email'])) {
                $firstName = $data['first_name'] ?? explode(' ', trim($data['full_name'] ?? ''), 2)[0] ?? '';
                $lastName = $data['last_name'] ?? (explode(' ', trim($data['full_name'] ?? ''), 2)[1] ?? '');

                $assistantUser = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'first_name' => $firstName,
                        'last_name'  => $lastName,
                        'phone'      => $data['phone'] ?? null,
                        'password'   => bcrypt('runway7'),
                        'role'       => 'assistant',
                        'status'     => 'active',
                    ]
                );
            }

            $firstName = $data['first_name'] ?? explode(' ', trim($data['full_name'] ?? ''), 2)[0] ?? '';
            $lastName = $data['last_name'] ?? (explode(' ', trim($data['full_name'] ?? ''), 2)[1] ?? '');

            $assistant = DesignerAssistant::create([
                'designer_id' => $designer->id,
                'user_id'     => $assistantUser?->id,
                'event_id'    => $eventId,
                'first_name'  => $firstName,
                'last_name'   => $lastName,
                'document_id' => $data['document_id'] ?? null,
                'phone'       => $data['phone'] ?? null,
                'email'       => $data['email'] ?? null,
                'status'      => $data['status'] ?? 'registered',
            ]);

            if ($assistantUser) {
                $this->syncAssistantPass($assistantUser, $designer, $eventId, $issuedById);
            }

            return $assistant;
        });
    }

    /**
     * Crear o actualizar el pase de un asistente.
     * Hereda los valid_days del pase del diseñador.
     */
    public function syncAssistantPass(User $assistant, User $designer, int $eventId, int $issuedById): void
    {
        // Heredar valid_days del pase activo del diseñador
        $designerPass = EventPass::where('user_id', $designer->id)
            ->where('event_id', $eventId)
            ->where('status', '!=', 'cancelled')
            ->first();

        $validDays = $designerPass?->valid_days;

        $pass = EventPass::where('user_id', $assistant->id)
            ->where('event_id', $eventId)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($pass) {
            $pass->update(['valid_days' => $validDays]);
        } else {
            EventPass::create([
                'event_id'     => $eventId,
                'user_id'      => $assistant->id,
                'issued_by'    => $issuedById,
                'qr_code'      => EventPass::generateQrCode(),
                'pass_type'    => 'assistant',
                'holder_name'  => $assistant->full_name,
                'holder_email' => $assistant->email,
                'valid_days'   => $validDays,
                'status'       => 'active',
            ]);
        }
    }

    /**
     * Eliminar asistente de un diseñador y cancelar su pase.
     */
    public function removeAssistant(DesignerAssistant $assistant): void
    {
        if ($assistant->user_id) {
            EventPass::where('user_id', $assistant->user_id)
                ->where('event_id', $assistant->event_id)
                ->where('status', '!=', 'cancelled')
                ->update(['status' => 'cancelled']);
        }

        $assistant->delete();
    }

    /**
     * Actualizar material de un diseñador.
     */
    public function updateMaterial(DesignerMaterial $material, array $data): DesignerMaterial
    {
        $material->update($data);

        return $material->fresh();
    }

    /**
     * Actualizar display de un diseñador.
     */
    public function updateDisplay(DesignerDisplay $display, array $data): DesignerDisplay
    {
        $display->update($data);

        return $display->fresh();
    }

    /**
     * Cancelar participación de un diseñador en un evento (mantiene historial).
     * Cancela el event_designer, todos sus shows y pases, pero conserva materiales, display y asistentes.
     */
    public function cancelEventParticipation(User $user, int $eventId): void
    {
        $event = Event::findOrFail($eventId);

        DB::transaction(function () use ($event, $user) {
            // Cancelar participación en el evento
            $event->designers()->updateExistingPivot($user->id, ['status' => 'cancelled']);

            // Cancelar todos sus shows en este evento
            $showIds = Show::whereIn('event_day_id', $event->eventDays()->pluck('id'))->pluck('id');
            DB::table('show_designer')
                ->where('designer_id', $user->id)
                ->whereIn('show_id', $showIds)
                ->update(['status' => 'cancelled']);

            // Cancelar pase del diseñador
            EventPass::where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->where('status', '!=', 'cancelled')
                ->update(['status' => 'cancelled']);

            // Cancelar pases de los asistentes
            $assistantUserIds = DesignerAssistant::where('designer_id', $user->id)
                ->where('event_id', $event->id)
                ->whereNotNull('user_id')
                ->pluck('user_id');

            if ($assistantUserIds->isNotEmpty()) {
                EventPass::whereIn('user_id', $assistantUserIds)
                    ->where('event_id', $event->id)
                    ->where('status', '!=', 'cancelled')
                    ->update(['status' => 'cancelled']);
            }
        });
    }

    /**
     * Quitar diseñador de un evento (elimina shows, materiales, display, asistentes y cancela el pase).
     */
    public function removeFromEvent(User $user, int $eventId): void
    {
        $event = Event::findOrFail($eventId);

        DB::transaction(function () use ($event, $user) {
            $showIds = Show::whereIn('event_day_id', $event->eventDays()->pluck('id'))->pluck('id');

            DB::table('show_designer')
                ->where('designer_id', $user->id)
                ->whereIn('show_id', $showIds)
                ->delete();

            DesignerMaterial::where('designer_id', $user->id)
                ->where('event_id', $event->id)
                ->delete();

            DesignerDisplay::where('designer_id', $user->id)
                ->where('event_id', $event->id)
                ->delete();

            DesignerAssistant::where('designer_id', $user->id)
                ->where('event_id', $event->id)
                ->delete();

            // Cancelar pase del diseñador
            EventPass::where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->where('status', '!=', 'cancelled')
                ->update(['status' => 'cancelled']);

            // Cancelar pases de los asistentes del diseñador en este evento
            $assistantUserIds = DesignerAssistant::where('designer_id', $user->id)
                ->where('event_id', $event->id)
                ->whereNotNull('user_id')
                ->pluck('user_id');

            if ($assistantUserIds->isNotEmpty()) {
                EventPass::whereIn('user_id', $assistantUserIds)
                    ->where('event_id', $event->id)
                    ->where('status', '!=', 'cancelled')
                    ->update(['status' => 'cancelled']);
            }

            $event->designers()->detach($user->id);
        });
    }

    /**
     * Crear o actualizar el pase de un diseñador para un evento.
     * valid_days se calcula a partir de los días únicos de sus shows asignados.
     */
    public function syncDesignerPass(User $designer, int $eventId, int $issuedById): void
    {
        // Obtener días únicos de los shows confirmados del diseñador en este evento
        $dayIds = Show::whereIn('event_day_id',
                EventDay::where('event_id', $eventId)->pluck('id')
            )
            ->whereHas('designers', fn ($q) => $q->where('designer_id', $designer->id)
                ->where('show_designer.status', '!=', 'cancelled'))
            ->pluck('event_day_id')
            ->unique()
            ->values()
            ->toArray();

        $validDays = empty($dayIds) ? null : $dayIds;

        $pass = EventPass::where('user_id', $designer->id)
            ->where('event_id', $eventId)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($pass) {
            $pass->update(['valid_days' => $validDays]);
        } else {
            EventPass::create([
                'event_id'     => $eventId,
                'user_id'      => $designer->id,
                'issued_by'    => $issuedById,
                'qr_code'      => EventPass::generateQrCode(),
                'pass_type'    => 'designer',
                'holder_name'  => $designer->full_name,
                'holder_email' => $designer->email,
                'valid_days'   => $validDays,
                'status'       => 'active',
            ]);
        }

        // Sincronizar valid_days en los pases activos de los asistentes del diseñador
        $assistantUserIds = DesignerAssistant::where('designer_id', $designer->id)
            ->where('event_id', $eventId)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        if ($assistantUserIds->isNotEmpty()) {
            EventPass::whereIn('user_id', $assistantUserIds)
                ->where('event_id', $eventId)
                ->where('status', '!=', 'cancelled')
                ->update(['valid_days' => $validDays]);
        }
    }
}
