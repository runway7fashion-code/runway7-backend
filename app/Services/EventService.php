<?php

namespace App\Services;

use App\Models\CastingSlot;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\FittingAssignment;
use App\Models\FittingSlot;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventService
{
    public function createEvent(array $eventData, array $days, array $timeSlots = [], bool $applySameSchedule = true): Event
    {
        return DB::transaction(function () use ($eventData, $days, $timeSlots, $applySameSchedule) {
            if (empty($eventData['slug'])) {
                $eventData['slug'] = Str::slug($eventData['name']);
            }

            $event = Event::create($eventData);

            foreach ($days as $index => $day) {
                $eventDay = $event->eventDays()->create([
                    'date'        => $day['date'],
                    'label'       => $day['label'],
                    'type'        => $day['type'],
                    'start_time'  => $day['start_time'] ?? null,
                    'end_time'    => $day['end_time'] ?? null,
                    'status'      => 'scheduled',
                    'description' => $day['description'] ?? null,
                    'order'       => $index,
                ]);

                if ($eventDay->isCasting()) {
                    if (!empty($day['casting_slots'])) {
                        // Usar slots personalizados
                        $this->createCustomCastingSlots($eventDay, $day['casting_slots']);
                    } elseif (isset($day['casting_start'], $day['casting_end'], $day['casting_interval'])) {
                        // Generar automáticamente por intervalo
                        $this->generateCastingSlots(
                            $eventDay,
                            $day['casting_start'],
                            $day['casting_end'],
                            (int) $day['casting_interval'],
                            (int) ($day['casting_capacity'] ?? 50)
                        );
                    }
                }

                // Fitting slots: para días tipo "fitting" o show_day con fitting configurado
                if (isset($day['fitting_start'], $day['fitting_end'], $day['fitting_interval'])) {
                    $eventDay->update([
                        'fitting_start'    => $day['fitting_start'],
                        'fitting_end'      => $day['fitting_end'],
                        'fitting_interval' => (int) $day['fitting_interval'],
                    ]);
                    $this->generateFittingSlots(
                        $eventDay,
                        $day['fitting_start'],
                        $day['fitting_end'],
                        (int) $day['fitting_interval'],
                        (int) ($day['fitting_capacity'] ?? 5)
                    );
                }

                if ($eventDay->isShowDay()) {
                    $slots = $applySameSchedule
                        ? $timeSlots
                        : ($day['time_slots'] ?? []);

                    foreach ($slots as $order => $time) {
                        $eventDay->shows()->create([
                            'name'           => $eventDay->label . ' – ' . Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A'),
                            'scheduled_time' => $time,
                            'order'          => $order,
                            'status'         => 'scheduled',
                        ]);
                    }
                }
            }

            return $event->load('eventDays.shows', 'eventDays.castingSlots', 'eventDays.fittingSlots');
        });
    }

    public function generateShows(Event $event, array $timeSlots): int
    {
        $count = 0;

        DB::transaction(function () use ($event, $timeSlots, &$count) {
            $showDays = $event->eventDays()->where('type', 'show_day')->get();

            foreach ($showDays as $day) {
                foreach ($timeSlots as $order => $time) {
                    if (!$day->shows()->where('scheduled_time', $time)->exists()) {
                        $day->shows()->create([
                            'name'           => $day->label . ' – ' . Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A'),
                            'scheduled_time' => $time,
                            'order'          => $order,
                            'status'         => 'scheduled',
                        ]);
                        $count++;
                    }
                }
            }
        });

        return $count;
    }

    public function createCustomCastingSlots(EventDay $day, array $slots): int
    {
        $newTimes = [];
        foreach ($slots as $slot) {
            $time = substr($slot['time'], 0, 5);
            $capacity = (int) ($slot['capacity'] ?? 50);
            $newTimes[] = $time;
            CastingSlot::firstOrCreate(
                ['event_day_id' => $day->id, 'time' => $time],
                ['capacity' => $capacity, 'booked' => 0]
            );
        }

        // Eliminar slots que ya no están (solo sin bookings)
        $day->castingSlots()
            ->whereNotIn('time', $newTimes)
            ->where('booked', 0)
            ->delete();

        // Actualizar capacidad individual
        foreach ($slots as $slot) {
            $day->castingSlots()
                ->where('time', substr($slot['time'], 0, 5))
                ->update(['capacity' => (int) ($slot['capacity'] ?? 50)]);
        }

        return count($slots);
    }

    public function generateCastingSlots(EventDay $day, string $startTime, string $endTime, int $intervalMinutes, int $capacity = 50): int
    {
        $count    = 0;
        $current  = Carbon::createFromFormat('H:i', substr($startTime, 0, 5));
        $end      = Carbon::createFromFormat('H:i', substr($endTime, 0, 5));
        $newTimes = [];

        // Generar los nuevos slots
        while ($current->lte($end)) {
            $time = $current->format('H:i');
            $newTimes[] = $time;
            CastingSlot::firstOrCreate(
                ['event_day_id' => $day->id, 'time' => $time],
                ['capacity' => $capacity, 'booked' => 0]
            );
            $current->addMinutes($intervalMinutes);
            $count++;
        }

        // Eliminar slots que ya no corresponden al nuevo intervalo (solo si no tienen bookings)
        $day->castingSlots()
            ->whereNotIn('time', $newTimes)
            ->where('booked', 0)
            ->delete();

        // Actualizar capacidad en slots existentes si cambió
        $day->castingSlots()
            ->whereIn('time', $newTimes)
            ->update(['capacity' => $capacity]);

        return $count;
    }

    public function generateFittingSlots(EventDay $day, string $startTime, string $endTime, int $intervalMinutes, int $capacity = 5): int
    {
        $count    = 0;
        $current  = Carbon::createFromFormat('H:i', substr($startTime, 0, 5));
        $end      = Carbon::createFromFormat('H:i', substr($endTime, 0, 5));
        $newTimes = [];

        while ($current->lte($end)) {
            $time = $current->format('H:i');
            $newTimes[] = $time;
            FittingSlot::firstOrCreate(
                ['event_day_id' => $day->id, 'time' => $time],
                ['capacity' => $capacity]
            );
            $current->addMinutes($intervalMinutes);
            $count++;
        }

        // Eliminar slots sin asignaciones que ya no corresponden
        $day->fittingSlots()
            ->whereNotIn('time', $newTimes)
            ->whereDoesntHave('assignments')
            ->delete();

        // Actualizar capacidad en slots existentes
        $day->fittingSlots()
            ->whereIn('time', $newTimes)
            ->update(['capacity' => $capacity]);

        return $count;
    }

    public function assignDesignerToFitting(FittingSlot $slot, int $designerId, ?string $notes = null): FittingAssignment
    {
        if ($slot->assignments()->where('designer_id', $designerId)->exists()) {
            throw new \Exception('Este diseñador ya está asignado a este horario de fitting.');
        }

        return FittingAssignment::create([
            'fitting_slot_id' => $slot->id,
            'designer_id'     => $designerId,
            'notes'           => $notes,
        ]);
    }

    public function removeDesignerFromFitting(FittingSlot $slot, int $designerId): void
    {
        $slot->assignments()->where('designer_id', $designerId)->delete();
    }

    public function assignDesigner(Show $show, int $designerId, ?string $collectionName = null, ?int $order = null): Show
    {
        if ($show->designers()->where('designer_id', $designerId)->exists()) {
            throw new \Exception('Este diseñador ya está asignado a este show.');
        }

        $order = $order ?? ($show->designers()->count() + 1);

        $show->designers()->attach($designerId, [
            'order'           => $order,
            'collection_name' => $collectionName,
            'status'          => 'confirmed',
        ]);

        return $show->fresh(['designers.designerProfile']);
    }

    public function removeDesigner(Show $show, int $designerId): Show
    {
        DB::transaction(function () use ($show, $designerId) {
            // Remove models belonging to this designer in this show
            DB::table('show_model')
                ->where('show_id', $show->id)
                ->where('designer_id', $designerId)
                ->delete();
            $show->designers()->detach($designerId);
        });

        return $show->fresh(['designers.designerProfile']);
    }

    public function duplicateEvent(Event $sourceEvent, array $newEventData): Event
    {
        return DB::transaction(function () use ($sourceEvent, $newEventData) {
            if (empty($newEventData['slug'])) {
                $newEventData['slug'] = Str::slug($newEventData['name']);
            }

            $newEvent = Event::create($newEventData);

            foreach ($sourceEvent->eventDays()->with(['shows', 'castingSlots', 'fittingSlots'])->get() as $day) {
                $newDay = $newEvent->eventDays()->create([
                    'label'            => $day->label,
                    'type'             => $day->type,
                    'order'            => $day->order,
                    'start_time'       => $day->start_time,
                    'end_time'         => $day->end_time,
                    'status'           => 'scheduled',
                    'date'             => $day->date,
                    'description'      => $day->description,
                    'fitting_start'    => $day->fitting_start,
                    'fitting_end'      => $day->fitting_end,
                    'fitting_interval' => $day->fitting_interval,
                ]);

                foreach ($day->shows as $show) {
                    $newDay->shows()->create([
                        'name'           => $show->name,
                        'scheduled_time' => $show->scheduled_time,
                        'order'          => $show->order,
                        'model_slots'    => $show->model_slots,
                        'status'         => 'scheduled',
                        // Designer assignments NOT copied — must be reassigned
                    ]);
                }

                foreach ($day->castingSlots as $slot) {
                    $newDay->castingSlots()->create([
                        'time'     => $slot->time,
                        'capacity' => $slot->capacity,
                        'booked'   => 0,
                    ]);
                }

                foreach ($day->fittingSlots as $slot) {
                    $newDay->fittingSlots()->create([
                        'time'     => $slot->time,
                        'capacity' => $slot->capacity,
                    ]);
                }
            }

            return $newEvent->load('eventDays.shows', 'eventDays.castingSlots', 'eventDays.fittingSlots');
        });
    }

    public function updateEvent(Event $event, array $eventData, array $days): Event
    {
        return DB::transaction(function () use ($event, $eventData, $days) {
            $event->update($eventData);

            foreach ($days as $index => $dayData) {
                if (isset($dayData['id'])) {
                    $day = EventDay::find($dayData['id']);
                    if ($day) {
                        $day->update([
                            'label'            => $dayData['label'],
                            'type'             => $dayData['type'],
                            'start_time'       => $dayData['start_time'] ?? null,
                            'end_time'         => $dayData['end_time'] ?? null,
                            'description'      => $dayData['description'] ?? null,
                            'order'            => $index,
                            'fitting_start'    => $dayData['fitting_start'] ?? null,
                            'fitting_end'      => $dayData['fitting_end'] ?? null,
                            'fitting_interval' => $dayData['fitting_interval'] ?? null,
                        ]);

                        if ($dayData['type'] === 'casting') {
                            if (!empty($dayData['casting_slots'])) {
                                $this->createCustomCastingSlots($day, $dayData['casting_slots']);
                            } elseif (isset($dayData['casting_start'], $dayData['casting_end'], $dayData['casting_interval'])) {
                                $this->generateCastingSlots(
                                    $day,
                                    $dayData['casting_start'],
                                    $dayData['casting_end'],
                                    (int) $dayData['casting_interval'],
                                    (int) ($dayData['casting_capacity'] ?? 50)
                                );
                            }
                        }

                        if (isset($dayData['fitting_start'], $dayData['fitting_end'], $dayData['fitting_interval'])) {
                            $this->generateFittingSlots(
                                $day,
                                $dayData['fitting_start'],
                                $dayData['fitting_end'],
                                (int) $dayData['fitting_interval'],
                                (int) ($dayData['fitting_capacity'] ?? 5)
                            );
                        }
                    }
                } else {
                    $newDay = $event->eventDays()->create([
                        'date'             => $dayData['date'],
                        'label'            => $dayData['label'],
                        'type'             => $dayData['type'],
                        'start_time'       => $dayData['start_time'] ?? null,
                        'end_time'         => $dayData['end_time'] ?? null,
                        'status'           => 'scheduled',
                        'description'      => $dayData['description'] ?? null,
                        'order'            => $index,
                        'fitting_start'    => $dayData['fitting_start'] ?? null,
                        'fitting_end'      => $dayData['fitting_end'] ?? null,
                        'fitting_interval' => $dayData['fitting_interval'] ?? null,
                    ]);

                    if ($newDay->isCasting()) {
                        if (!empty($dayData['casting_slots'])) {
                            $this->createCustomCastingSlots($newDay, $dayData['casting_slots']);
                        } elseif (isset($dayData['casting_start'], $dayData['casting_end'], $dayData['casting_interval'])) {
                            $this->generateCastingSlots(
                                $newDay,
                                $dayData['casting_start'],
                                $dayData['casting_end'],
                                (int) $dayData['casting_interval'],
                                (int) ($dayData['casting_capacity'] ?? 50)
                            );
                        }
                    }

                    if (isset($dayData['fitting_start'], $dayData['fitting_end'], $dayData['fitting_interval'])) {
                        $this->generateFittingSlots(
                            $newDay,
                            $dayData['fitting_start'],
                            $dayData['fitting_end'],
                            (int) $dayData['fitting_interval'],
                            (int) ($dayData['fitting_capacity'] ?? 5)
                        );
                    }
                }
            }

            return $event->fresh(['eventDays.shows', 'eventDays.castingSlots', 'eventDays.fittingSlots']);
        });
    }
}
