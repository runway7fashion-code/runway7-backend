<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventPass;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ModelService
{
    /**
     * Crear una modelo completa: usuario + perfil + asignación opcional a evento.
     */
    public function createModel(array $userData, array $profileData, ?int $eventId = null, ?string $castingTime = null, string $status = 'pending'): User
    {
        return DB::transaction(function () use ($userData, $profileData, $eventId, $castingTime, $status) {
            $user = User::create([
                'first_name' => $userData['first_name'],
                'last_name'  => $userData['last_name'],
                'email'      => $userData['email'],
                'phone'      => $userData['phone'] ?? null,
                'password'   => bcrypt('runway7'),
                'role'       => 'model',
                'status'     => $status,
            ]);

            $user->modelProfile()->create($profileData);

            if ($eventId) {
                $this->assignToEvent($user, $eventId, $castingTime);
            }

            return $user->load('modelProfile');
        });
    }

    /**
     * Actualizar datos de una modelo.
     */
    public function updateModel(User $user, array $userData, array $profileData): User
    {
        return DB::transaction(function () use ($user, $userData, $profileData) {
            $user->update(collect($userData)->except('password')->toArray());

            $user->modelProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            return $user->fresh('modelProfile');
        });
    }

    /**
     * Asignar modelo a un evento con casting slot opcional.
     * Auto-asigna participation_number basado en model_number_start del evento.
     */
    public function assignToEvent(User $user, int $eventId, ?string $castingTime = null): void
    {
        $event = Event::findOrFail($eventId);

        if ($event->models()->where('model_id', $user->id)->exists()) {
            throw new \Exception('La modelo ya está asignada a este evento.');
        }

        $pivotData = [
            'status' => 'invited',
        ];

        if ($castingTime) {
            $pivotData['casting_time']   = $castingTime;
            $pivotData['casting_status'] = 'scheduled';

            $castingDay = $event->eventDays()->where('type', 'casting')->first();
            if ($castingDay) {
                $slot = $castingDay->castingSlots()->where('time', $castingTime)->first();
                if ($slot) {
                    $slot->increment('booked');
                }
            }
        }

        $event->models()->attach($user->id, $pivotData);
    }

    /**
     * Crear o actualizar el pase de una modelo para un evento.
     * valid_days = día de casting (si tiene turno) + días de shows confirmados/reservados.
     */
    public function syncModelPass(User $model, int $eventId, int $issuedById): void
    {
        $eventDayIds = EventDay::where('event_id', $eventId)->pluck('id');

        $dayIds = [];

        // Día de casting (si la modelo tiene casting_time asignado en este evento)
        $eventPivot = Event::findOrFail($eventId)->models()
            ->where('model_id', $model->id)
            ->first();

        if ($eventPivot && $eventPivot->pivot->casting_time) {
            $castingDay = EventDay::where('event_id', $eventId)
                ->where('type', 'casting')
                ->first();
            if ($castingDay) {
                $dayIds[] = $castingDay->id;
            }
        }

        // Días de shows confirmados o reservados
        $showDayIds = Show::whereIn('event_day_id', $eventDayIds)
            ->whereHas('models', fn ($q) => $q->where('model_id', $model->id)
                ->whereIn('show_model.status', ['reserved', 'confirmed']))
            ->pluck('event_day_id')
            ->unique()
            ->values()
            ->toArray();

        $dayIds = array_values(array_unique(array_merge($dayIds, $showDayIds)));
        $validDays = empty($dayIds) ? null : $dayIds;

        $pass = EventPass::where('user_id', $model->id)
            ->where('event_id', $eventId)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($pass) {
            $pass->update(['valid_days' => $validDays]);
        } else {
            EventPass::create([
                'event_id'     => $eventId,
                'user_id'      => $model->id,
                'issued_by'    => $issuedById,
                'qr_code'      => EventPass::generateQrCode(),
                'pass_type'    => 'model',
                'holder_name'  => $model->full_name,
                'holder_email' => $model->email,
                'valid_days'   => $validDays,
                'status'       => 'active',
            ]);
        }
    }

    /**
     * Quitar modelo de un evento (libera casting slot, remueve shows y cancela pase).
     */
    public function removeFromEvent(User $user, int $eventId): void
    {
        $event = Event::findOrFail($eventId);

        DB::transaction(function () use ($event, $user) {
            $pivot = $event->models()->where('model_id', $user->id)->first();

            if ($pivot && $pivot->pivot->casting_time) {
                $castingDay = $event->eventDays()->where('type', 'casting')->first();
                if ($castingDay) {
                    $slot = $castingDay->castingSlots()->where('time', $pivot->pivot->casting_time)->first();
                    if ($slot && $slot->booked > 0) {
                        $slot->decrement('booked');
                    }
                }
            }

            $showIds = Show::whereIn('event_day_id', $event->eventDays()->pluck('id'))->pluck('id');
            DB::table('show_model')->where('model_id', $user->id)->whereIn('show_id', $showIds)->delete();

            // Cancelar pase de la modelo
            EventPass::where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->where('status', '!=', 'cancelled')
                ->update(['status' => 'cancelled']);

            $event->models()->detach($user->id);
        });
    }

    /**
     * Subir una foto al comp card (posición 1-4).
     */
    public function uploadCompCardPhoto(User $user, int $position, $file): string
    {
        if (!in_array($position, [1, 2, 3, 4])) {
            throw new \InvalidArgumentException('Posición inválida. Debe ser 1, 2, 3 o 4.');
        }

        $field    = "photo_{$position}";
        $profile  = $user->modelProfile;

        // Eliminar foto anterior si existe
        if ($profile->$field) {
            Storage::disk('public')->delete($profile->$field);
        }

        $path = $file->store("models/{$user->id}/compcard", 'public');
        $profile->update([$field => $path]);

        // Actualizar estado compcard_completed
        $fresh = $profile->fresh();
        $profile->update(['compcard_completed' => $fresh->isCompCardComplete()]);

        return $path;
    }

    /**
     * Eliminar foto de comp card (posición 1-4).
     */
    public function deleteCompCardPhoto(User $user, int $position): void
    {
        if (!in_array($position, [1, 2, 3, 4])) {
            throw new \InvalidArgumentException('Posición inválida. Debe ser 1, 2, 3 o 4.');
        }

        $field   = "photo_{$position}";
        $profile = $user->modelProfile;

        if ($profile->$field) {
            Storage::disk('public')->delete($profile->$field);
            $profile->update([$field => null, 'compcard_completed' => false]);
        }
    }

    /**
     * Subir foto de perfil del usuario.
     */
    public function uploadProfilePicture(User $user, $file): string
    {
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $path = $file->store("models/{$user->id}", 'public');
        $user->update(['profile_picture' => $path]);

        return $path;
    }

    /**
     * Eliminar foto de perfil del usuario.
     */
    public function deleteProfilePicture(User $user): void
    {
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
            $user->update(['profile_picture' => null]);
        }
    }

    /**
     * Eliminar una modelo completamente: archivos del storage + hard delete de BD.
     */
    public function deleteModel(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Eliminar toda la carpeta del storage (foto de perfil + comp card)
            Storage::disk('public')->deleteDirectory("models/{$user->id}");

            // Hard delete: elimina definitivamente de la BD (cascade borra model_profile,
            // event_model, show_model, event_passes, device_tokens automáticamente)
            $user->forceDelete();
        });
    }

    /**
     * Enviar email de bienvenida a la modelo.
     * TODO: Implementar con Mailgun.
     */
    public function sendWelcomeEmail(User $user): void
    {
        // Mail::to($user->email)->send(new ModelWelcomeMail($user));
    }
}
