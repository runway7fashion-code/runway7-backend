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
        // 1. Designer must be assigned to this show.
        if (!$show->designers()->where('designer_id', $designer->id)->exists()) {
            throw new \Exception('You are not assigned to this show.');
        }

        // 2. Reject duplicate / re-send after rejection for the same (show, model, designer).
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

        // 3. Same-show rule: another designer in this same show already has the model confirmed/reserved.
        $sameShowTaken = DB::table('show_model')
            ->where('show_id', $show->id)
            ->where('model_id', $model->id)
            ->whereIn('status', ['confirmed', 'reserved'])
            ->exists();
        if ($sameShowTaken) {
            throw new \Exception('This model is already booked for this show by another designer.');
        }

        // 4. Adjacent-show rule: model is confirmed/reserved in the show immediately
        // before or after this one within the same day (ordered chronologically by
        // scheduled_time). Two back-to-back shows are not allowed.
        if ($this->hasAdjacentShowConflict($show, $model)) {
            throw new \Exception('This model has another show right before or after this one. Models cannot take back-to-back shows.');
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
        if ($accept && $this->hasAdjacentShowConflict($show, $model)) {
            throw new \Exception('Schedule conflict: the model has another show right before or after this one.');
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

        // Update the specific (show, model, designer) row.
        DB::table('show_model')
            ->where('show_id', $show->id)
            ->where('model_id', $model->id)
            ->where('designer_id', $designer->id)
            ->update($pivotData);

        if ($accept) {
            // Auto-reject every OTHER pending request from designers in this same
            // show (the model just picked one of them). Notify each so they see
            // "model rejected your request" without the model having to do it.
            $autoRejectedIds = DB::table('show_model')
                ->where('show_id', $show->id)
                ->where('model_id', $model->id)
                ->where('designer_id', '!=', $designer->id)
                ->where('status', 'requested')
                ->pluck('designer_id');

            if ($autoRejectedIds->isNotEmpty()) {
                DB::table('show_model')
                    ->where('show_id', $show->id)
                    ->where('model_id', $model->id)
                    ->whereIn('designer_id', $autoRejectedIds)
                    ->update([
                        'status'           => 'rejected',
                        'responded_at'     => now(),
                        'rejection_reason' => 'Model selected another designer for this show.',
                    ]);

                $modelName = trim($model->first_name . ' ' . $model->last_name);
                foreach ($autoRejectedIds as $designerId) {
                    SendCastingNotificationJob::dispatch(
                        recipientId: (int) $designerId,
                        title:       "{$modelName} declined your request",
                        body:        "for {$show->name}. Reason: Model selected another designer for this show.",
                        showId:      $show->id,
                        senderId:    $model->id,
                    );
                }
            }

            // Mark model as 'selected' in event_model.
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
     * Adjacent-show rule: returns true when the model is already confirmed or
     * reserved in the show that comes immediately before OR immediately after
     * this one within the same event_day, ordered chronologically by
     * scheduled_time.
     *
     * Adjacency is by SHOW position in the day's chronological list, not by
     * absolute time gap or by the `order` column. Two shows are adjacent when
     * no other show exists between them in time within the day.
     */
    public function hasAdjacentShowConflict(Show $show, User $model): bool
    {
        $dayShows = Show::where('event_day_id', $show->event_day_id)
            ->orderBy('scheduled_time')
            ->orderBy('id') // tie-breaker if two shows share scheduled_time
            ->get();

        $currentIndex = $dayShows->search(fn ($s) => $s->id === $show->id);
        if ($currentIndex === false) return false;

        foreach ([$currentIndex - 1, $currentIndex + 1] as $neighborIdx) {
            if ($neighborIdx < 0 || $neighborIdx >= $dayShows->count()) continue;
            $neighbor = $dayShows[$neighborIdx];

            $hasConflict = DB::table('show_model')
                ->where('show_id', $neighbor->id)
                ->where('model_id', $model->id)
                ->whereIn('status', ['confirmed', 'reserved'])
                ->exists();

            if ($hasConflict) return true;
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
