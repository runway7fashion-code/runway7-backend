<?php

namespace App\Services;

use App\Models\CastingSlot;
use App\Models\Event;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CastingService
{
    public function assignModelToCastingSlot(Event $event, User $model, CastingSlot $slot): void
    {
        DB::transaction(function () use ($event, $model, $slot) {
            if (!$slot->isAvailable()) {
                throw new \Exception('Este horario de casting está lleno.');
            }

            $event->models()->syncWithoutDetaching([
                $model->id => [
                    'casting_time'   => $slot->time,
                    'casting_status' => 'scheduled',
                ],
            ]);

            $slot->increment('booked');
        });
    }

    public function checkInModelToCasting(Event $event, User $model, int $participationNumber): void
    {
        $event->models()->updateExistingPivot($model->id, [
            'participation_number'  => $participationNumber,
            'casting_status'        => 'checked_in',
            'casting_checked_in_at' => now(),
            'status'                => 'confirmed',
        ]);
    }

    public function requestModelForShow(Show $show, User $model, User $designer): array
    {
        // Verify designer is assigned to this show
        if (!$show->designers()->where('designer_id', $designer->id)->exists()) {
            throw new \Exception('Este diseñador no está asignado a este show.');
        }

        if ($this->hasConsecutiveShowConflict($show, $model)) {
            throw new \Exception('La modelo ya tiene un show consecutivo asignado. No puede tener dos shows seguidos.');
        }

        // A model can only have one request per designer per show
        if ($show->models()->where('model_id', $model->id)->wherePivot('designer_id', $designer->id)->exists()) {
            throw new \Exception('Ya existe una solicitud para esta modelo con este diseñador en este show.');
        }

        $show->models()->attach($model->id, [
            'designer_id'  => $designer->id,
            'status'       => 'requested',
            'requested_by' => $designer->id,
            'requested_at' => now(),
        ]);

        return ['message' => 'Solicitud enviada correctamente.'];
    }

    public function respondToRequest(Show $show, User $model, bool $accept, ?string $rejectionReason = null): void
    {
        if ($accept && $this->hasConsecutiveShowConflict($show, $model)) {
            throw new \Exception('Conflicto de horario: la modelo tiene un show consecutivo.');
        }

        $pivotData = [
            'status'       => $accept ? 'confirmed' : 'rejected',
            'responded_at' => now(),
        ];

        if ($accept) {
            $pivotData['confirmed_at'] = now();
        }

        if (!$accept && $rejectionReason) {
            $pivotData['rejection_reason'] = $rejectionReason;
        }

        $show->models()->updateExistingPivot($model->id, $pivotData);
    }

    public function hasConsecutiveShowConflict(Show $show, User $model): bool
    {
        $dayShows = Show::where('event_day_id', $show->event_day_id)
            ->orderBy('order')
            ->get();

        $currentIndex = $dayShows->search(fn($s) => $s->id === $show->id);

        if ($currentIndex > 0) {
            $previousShow = $dayShows[$currentIndex - 1];
            if ($previousShow->models()
                ->where('model_id', $model->id)
                ->whereIn('show_model.status', ['confirmed', 'reserved'])
                ->exists()) {
                return true;
            }
        }

        if ($currentIndex < $dayShows->count() - 1) {
            $nextShow = $dayShows[$currentIndex + 1];
            if ($nextShow->models()
                ->where('model_id', $model->id)
                ->whereIn('show_model.status', ['confirmed', 'reserved'])
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    public function getNextParticipationNumber(Event $event): int
    {
        $maxNumber = DB::table('event_model')
            ->where('event_id', $event->id)
            ->max('participation_number');

        return ($maxNumber ?? 0) + 1;
    }
}
