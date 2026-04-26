<?php

namespace App\Services;

use App\Jobs\SendCastingNotificationJob;
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
                throw new \Exception('This casting slot is full.');
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

    public function confirmCastingSlot(Event $event, User $model): void
    {
        $pivot = $event->models()->where('model_id', $model->id)->first()?->pivot;

        if (!$pivot || !$pivot->casting_time) {
            throw new \Exception('This model does not have a casting slot assigned.');
        }

        if ($pivot->status !== 'invited') {
            throw new \Exception('This model has already responded to this invitation.');
        }

        $event->models()->updateExistingPivot($model->id, [
            'status' => 'confirmed',
        ]);
    }

    public function rejectCastingSlot(Event $event, User $model): void
    {
        $pivot = $event->models()->where('model_id', $model->id)->first()?->pivot;

        if (!$pivot || !$pivot->casting_time) {
            throw new \Exception('This model does not have a casting slot assigned.');
        }

        if ($pivot->status !== 'invited') {
            throw new \Exception('This model has already responded to this invitation.');
        }

        // Liberar el slot
        $castingDay = $event->eventDays()->where('type', 'casting')->first();
        if ($castingDay) {
            $slot = $castingDay->castingSlots()->where('time', $pivot->casting_time)->first();
            if ($slot && $slot->booked > 0) {
                $slot->decrement('booked');
            }
        }

        $event->models()->updateExistingPivot($model->id, [
            'casting_time'   => null,
            'casting_status' => 'scheduled',
            'status'         => 'invited',
        ]);
    }

    public function checkInModelToCasting(Event $event, User $model, int $participationNumber): void
    {
        $pivot = $event->models()->where('model_id', $model->id)->first()?->pivot;

        if ($pivot?->status !== 'confirmed') {
            throw new \Exception('The model must confirm their casting slot before checking in.');
        }

        $event->models()->updateExistingPivot($model->id, [
            'participation_number'  => $participationNumber,
            'casting_status'        => 'checked_in',
            'casting_checked_in_at' => now(),
        ]);
    }

    public function requestModelForShow(Show $show, User $model, User $designer, ?string $message = null): array
    {
        // Verify designer is assigned to this show
        if (!$show->designers()->where('designer_id', $designer->id)->exists()) {
            throw new \Exception('You are not assigned to this show.');
        }

        // Reject re-sends and duplicate requests
        $existingRequest = $show->models()
            ->where('model_id', $model->id)
            ->wherePivot('designer_id', $designer->id)
            ->first();

        if ($existingRequest) {
            if ($existingRequest->pivot->status === 'rejected') {
                throw new \Exception('This model already rejected this request. You cannot send it again.');
            }
            throw new \Exception('A request for this model already exists for this show.');
        }

        if ($conflict = $this->hasTimeConflict($show, $model)) {
            throw new \Exception("This model is already confirmed for another show at {$conflict}.");
        }

        if ($this->hasConsecutiveShowConflict($show, $model)) {
            throw new \Exception('This model is already booked for a consecutive show. Models cannot take two back-to-back shows.');
        }

        $show->models()->attach($model->id, [
            'designer_id'  => $designer->id,
            'status'       => 'requested',
            'requested_by' => $designer->id,
            'requested_at' => now(),
            'notes'        => $message,
        ]);

        // Notify the model
        $designerName = trim($designer->first_name . ' ' . $designer->last_name);
        $brandName = $designer->designerProfile?->brand_name;
        $displayName = $brandName ?: $designerName;
        SendCastingNotificationJob::dispatch(
            recipientId: $model->id,
            title: "New show request from {$displayName}",
            body: $message ?: "You have been requested for {$show->name}. Tap to review.",
            showId: $show->id,
            senderId: $designer->id,
        );

        return ['message' => 'Solicitud enviada correctamente.'];
    }

    public function respondToRequest(Show $show, User $model, User $designer, bool $accept, ?string $rejectionReason = null): void
    {
        if ($accept && $this->hasConsecutiveShowConflict($show, $model)) {
            throw new \Exception('Schedule conflict: the model has a consecutive show.');
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

        // Actualizar el registro específico de este diseñador
        DB::table('show_model')
            ->where('show_id', $show->id)
            ->where('model_id', $model->id)
            ->where('designer_id', $designer->id)
            ->update($pivotData);

        // Si acepta, cambiar casting_status a 'selected' en event_model
        if ($accept) {
            $show->loadMissing('eventDay');
            $eventId = $show->eventDay->event_id;

            DB::table('event_model')
                ->where('event_id', $eventId)
                ->where('model_id', $model->id)
                ->where('casting_status', 'checked_in')
                ->update(['casting_status' => 'selected']);
        }

        // Notify the designer of the response
        $modelName = trim($model->first_name . ' ' . $model->last_name);
        if ($accept) {
            SendCastingNotificationJob::dispatch(
                recipientId: $designer->id,
                title: "{$modelName} accepted your request",
                body: "for {$show->name}",
                showId: $show->id,
                senderId: $model->id,
            );
        } else {
            $reasonText = $rejectionReason ? "Reason: {$rejectionReason}" : "No reason provided.";
            SendCastingNotificationJob::dispatch(
                recipientId: $designer->id,
                title: "{$modelName} declined your request",
                body: "for {$show->name}. {$reasonText}",
                showId: $show->id,
                senderId: $model->id,
            );
        }
    }

    /**
     * Returns the conflicting time string (e.g. "1:00 PM") when the model is
     * already confirmed/reserved for another show in the same event_day at
     * the exact same scheduled_time. Null when there is no conflict.
     *
     * Two designers running shows at the same time cannot share the model.
     */
    public function hasTimeConflict(Show $show, User $model): ?string
    {
        if (!$show->scheduled_time) return null;

        $conflict = Show::where('event_day_id', $show->event_day_id)
            ->where('id', '!=', $show->id)
            ->where('scheduled_time', $show->scheduled_time)
            ->whereHas('models', fn ($q) => $q->where('users.id', $model->id)
                ->whereIn('show_model.status', ['confirmed', 'reserved']))
            ->first();

        if (!$conflict) return null;

        // Format scheduled_time (HH:MM:SS) → "1:00 PM"
        try {
            return \Carbon\Carbon::createFromFormat('H:i:s', $show->scheduled_time)->format('g:i A');
        } catch (\Exception $e) {
            return $show->scheduled_time;
        }
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
