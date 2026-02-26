<?php

namespace App\Services;

use App\Models\CastingSlot;
use App\Models\Event;
use App\Models\EventDay;
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

                if ($eventDay->isCasting()
                    && isset($day['casting_start'], $day['casting_end'], $day['casting_interval'])) {
                    $this->generateCastingSlots(
                        $eventDay,
                        $day['casting_start'],
                        $day['casting_end'],
                        (int) $day['casting_interval'],
                        (int) ($day['casting_capacity'] ?? 50)
                    );
                }

                if ($eventDay->isShowDay() && !empty($timeSlots)) {
                    $slots = $applySameSchedule
                        ? $timeSlots
                        : ($day['time_slots'] ?? $timeSlots);

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

            return $event->load('eventDays.shows', 'eventDays.castingSlots');
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

    public function generateCastingSlots(EventDay $day, string $startTime, string $endTime, int $intervalMinutes, int $capacity = 50): int
    {
        $count   = 0;
        $current = Carbon::createFromFormat('H:i', $startTime);
        $end     = Carbon::createFromFormat('H:i', $endTime);

        while ($current->lte($end)) {
            CastingSlot::firstOrCreate(
                ['event_day_id' => $day->id, 'time' => $current->format('H:i')],
                ['capacity' => $capacity, 'booked' => 0]
            );
            $current->addMinutes($intervalMinutes);
            $count++;
        }

        return $count;
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
            'status'          => 'assigned',
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

            foreach ($sourceEvent->eventDays()->with(['shows', 'castingSlots'])->get() as $day) {
                $newDay = $newEvent->eventDays()->create([
                    'label'       => $day->label,
                    'type'        => $day->type,
                    'order'       => $day->order,
                    'start_time'  => $day->start_time,
                    'end_time'    => $day->end_time,
                    'status'      => 'scheduled',
                    'date'        => $day->date,
                    'description' => $day->description,
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
            }

            return $newEvent->load('eventDays.shows', 'eventDays.castingSlots');
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
                        $oldType = $day->type;
                        $day->update([
                            'label'       => $dayData['label'],
                            'type'        => $dayData['type'],
                            'start_time'  => $dayData['start_time'] ?? null,
                            'end_time'    => $dayData['end_time'] ?? null,
                            'description' => $dayData['description'] ?? null,
                            'order'       => $index,
                        ]);

                        if ($dayData['type'] === 'casting'
                            && isset($dayData['casting_start'], $dayData['casting_end'], $dayData['casting_interval'])) {
                            $this->generateCastingSlots(
                                $day,
                                $dayData['casting_start'],
                                $dayData['casting_end'],
                                (int) $dayData['casting_interval'],
                                (int) ($dayData['casting_capacity'] ?? 50)
                            );
                        }
                    }
                } else {
                    $newDay = $event->eventDays()->create([
                        'date'        => $dayData['date'],
                        'label'       => $dayData['label'],
                        'type'        => $dayData['type'],
                        'start_time'  => $dayData['start_time'] ?? null,
                        'end_time'    => $dayData['end_time'] ?? null,
                        'status'      => 'scheduled',
                        'description' => $dayData['description'] ?? null,
                        'order'       => $index,
                    ]);

                    if ($newDay->isCasting()
                        && isset($dayData['casting_start'], $dayData['casting_end'], $dayData['casting_interval'])) {
                        $this->generateCastingSlots(
                            $newDay,
                            $dayData['casting_start'],
                            $dayData['casting_end'],
                            (int) $dayData['casting_interval'],
                            (int) ($dayData['casting_capacity'] ?? 50)
                        );
                    }
                }
            }

            return $event->fresh(['eventDays.shows', 'eventDays.castingSlots']);
        });
    }
}
