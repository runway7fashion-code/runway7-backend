<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventDay;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(EventService::class);

        // ---------------------------------------------------------------
        // New York Fashion Week September 2026
        // ---------------------------------------------------------------
        $event = Event::create([
            'name'       => 'New York Fashion Week September 2026',
            'slug'       => 'nyfw-september-2026',
            'city'       => 'New York',
            'venue'      => 'Spring Studios',
            'timezone'   => 'America/New_York',
            'start_date' => '2026-09-06',
            'end_date'   => '2026-09-14',
            'status'     => 'published',
            'description'=> 'The official Runway7 New York Fashion Week event — 9 days of fashion, casting, and culture.',
        ]);

        // Sep 6 – Load In (setup)
        $event->eventDays()->create([
            'date'       => '2026-09-06',
            'label'      => 'Load In',
            'type'       => 'setup',
            'start_time' => '16:00',
            'order'      => 0,
            'status'     => 'scheduled',
        ]);

        // Sep 7 – Model Casting (casting) — slots every 30 min 08:00–23:00
        $castingDay = $event->eventDays()->create([
            'date'       => '2026-09-07',
            'label'      => 'Model Casting',
            'type'       => 'casting',
            'start_time' => '08:00',
            'end_time'   => '23:00',
            'order'      => 1,
            'status'     => 'scheduled',
        ]);
        $service->generateCastingSlots($castingDay, '08:00', '23:00', 30, 50);

        // Sep 8 – Opening Night (show_day) 1 show: 19:00
        $day3 = $event->eventDays()->create([
            'date'       => '2026-09-08',
            'label'      => 'Opening Night - NYFW Kick Off',
            'type'       => 'show_day',
            'start_time' => '19:00',
            'order'      => 2,
            'status'     => 'scheduled',
        ]);
        $day3->shows()->create([
            'name'           => 'Opening Night - NYFW Kick Off – 7:00 PM',
            'scheduled_time' => '19:00:00',
            'order'          => 0,
            'status'         => 'scheduled',
        ]);

        // Sep 9–13 – Show Days with 6 shows each
        $showSlots = ['11:00', '13:00', '15:00', '17:00', '19:00', '21:00'];
        $showDayDates = [
            ['2026-09-09', 'Day 2 NYFW', 3],
            ['2026-09-10', 'Day 3 NYFW', 4],
            ['2026-09-11', 'Day 4 NYFW', 5],
            ['2026-09-12', 'Day 5 NYFW', 6],
            ['2026-09-13', 'Day 6 NYFW', 7],
        ];

        $day2 = null;
        foreach ($showDayDates as [$date, $label, $order]) {
            $day = $event->eventDays()->create([
                'date'       => $date,
                'label'      => $label,
                'type'       => 'show_day',
                'start_time' => '11:00',
                'end_time'   => '23:00',
                'order'      => $order,
                'status'     => 'scheduled',
            ]);

            foreach ($showSlots as $i => $time) {
                $day->shows()->create([
                    'name'           => "{$label} – " . \Illuminate\Support\Carbon::createFromFormat('H:i', $time)->format('g:i A'),
                    'scheduled_time' => $time . ':00',
                    'order'          => $i,
                    'status'         => 'scheduled',
                ]);
            }

            if ($date === '2026-09-09') {
                $day2 = $day;
            }
        }

        // Sep 14 – Award Ceremony
        $event->eventDays()->create([
            'date'       => '2026-09-14',
            'label'      => 'Award Ceremony',
            'type'       => 'ceremony',
            'start_time' => '19:00',
            'order'      => 8,
            'status'     => 'scheduled',
        ]);

        // ---------------------------------------------------------------
        // Assign designers to first 2 shows of Sep 9
        // ---------------------------------------------------------------
        $designer1 = User::where('email', 'ale@nocturnadesign.com')->first();
        $designer2 = User::where('email', 'val@lunawhite.com')->first();

        if ($day2 && $designer1 && $designer2) {
            $shows = $day2->shows()->orderBy('order')->get();
            if ($shows->count() >= 2) {
                // Assign both designers to the 11am show (multi-designer showcase)
                $shows[0]->designers()->attach($designer1->id, [
                    'order'           => 1,
                    'collection_name' => 'Dark Elegance SS26',
                    'status'          => 'confirmed',
                ]);
                $shows[0]->designers()->attach($designer2->id, [
                    'order'           => 2,
                    'collection_name' => 'Monochrome Dreams',
                    'status'          => 'confirmed',
                ]);

                // Also assign designer2 individually to the 1pm show
                $shows[1]->designers()->attach($designer2->id, [
                    'order'           => 1,
                    'collection_name' => 'Monochrome Dreams – Evening',
                    'status'          => 'assigned',
                ]);
            }
        }

        // ---------------------------------------------------------------
        // Assign models to event with casting info
        // ---------------------------------------------------------------
        $model1 = User::where('email', 'sofia.rivera@models.com')->first();
        $model2 = User::where('email', 'isabella.chen@models.com')->first();

        if ($model1) {
            $event->models()->attach($model1->id, [
                'participation_number'  => 1,
                'casting_time'          => '08:00',
                'casting_status'        => 'checked_in',
                'casting_checked_in_at' => now(),
                'status'                => 'confirmed',
            ]);
        }

        if ($model2) {
            $event->models()->attach($model2->id, [
                'participation_number'  => 2,
                'casting_time'          => '08:30',
                'casting_status'        => 'checked_in',
                'casting_checked_in_at' => now(),
                'status'                => 'confirmed',
            ]);
        }
    }
}
