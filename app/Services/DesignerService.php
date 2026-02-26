<?php

namespace App\Services;

use App\Models\DesignerAssistant;
use App\Models\DesignerDisplay;
use App\Models\DesignerMaterial;
use App\Models\Event;
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
                'status'     => 'active',
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
            'model_casting_enabled' => $data['model_casting_enabled'] ?? true,
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
     * Crear materiales por defecto para un diseñador en un evento.
     */
    public function createDefaultMaterials(User $user, Event $event): void
    {
        $materials = [
            ['name' => 'Background',       'description' => 'Background video or image for the runway display',       'order' => 1],
            ['name' => 'Music',            'description' => 'Music track for the runway show',                         'order' => 2],
            ['name' => 'Images',           'description' => 'Collection images for promotional materials',             'order' => 3],
            ['name' => 'Runway Logo',      'description' => 'Logo to display on the runway screen',                    'order' => 4],
            ['name' => 'Bio',              'description' => 'Designer biography for the event program',                'order' => 5],
            ['name' => 'Hair Mood Board',  'description' => 'Hair styling mood board for models',                      'order' => 6],
            ['name' => 'Makeup Mood Board','description' => 'Makeup mood board for models',                            'order' => 7],
            ['name' => 'Brand Logo',       'description' => 'Official brand logo in high resolution',                  'order' => 8],
            ['name' => 'Designer Photo',   'description' => 'Professional photo of the designer',                      'order' => 9],
            ['name' => 'Artworks',         'description' => 'Artwork files for event graphics and displays',           'order' => 10],
        ];

        foreach ($materials as $material) {
            DesignerMaterial::create([
                'designer_id' => $user->id,
                'event_id'    => $event->id,
                'name'        => $material['name'],
                'description' => $material['description'],
                'type'        => 'production_element',
                'order'       => $material['order'],
                'status'      => 'pending',
            ]);
        }
    }

    /**
     * Agregar asistente a un diseñador en un evento.
     */
    public function addAssistant(User $user, int $eventId, array $data): DesignerAssistant
    {
        return DesignerAssistant::create([
            'designer_id' => $user->id,
            'event_id'    => $eventId,
            'full_name'   => $data['full_name'],
            'document_id' => $data['document_id'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'email'       => $data['email'] ?? null,
            'status'      => $data['status'] ?? 'registered',
        ]);
    }

    /**
     * Eliminar asistente de un diseñador.
     */
    public function removeAssistant(DesignerAssistant $assistant): void
    {
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
     * Quitar diseñador de un evento (elimina shows, materiales, display y asistentes).
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

            $event->designers()->detach($user->id);
        });
    }
}
