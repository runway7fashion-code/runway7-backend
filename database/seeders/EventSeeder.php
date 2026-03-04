<?php

namespace Database\Seeders;

use App\Models\DesignerAssistant;
use App\Models\DesignerDisplay;
use App\Models\DesignerMaterial;
use App\Models\DesignerPackage;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\User;
use App\Services\DesignerService;
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
        // Assign designers to event_designer pivot + shows
        // ---------------------------------------------------------------
        $designer1 = User::where('email', 'ale@nocturnadesign.com')->first();
        $designer2 = User::where('email', 'val@lunawhite.com')->first();
        $designerService = app(DesignerService::class);

        $premiumPkg  = DesignerPackage::where('slug', 'premium')->first();
        $platinumPkg = DesignerPackage::where('slug', 'platinum')->first();

        // Attach designers to event with package info
        if ($designer1) {
            $event->designers()->attach($designer1->id, [
                'status'                => 'confirmed',
                'package_id'            => $premiumPkg?->id,
                'looks'                 => 12,
                'model_casting_enabled' => true,
                'package_price'         => 5000.00,
                'notes'                 => 'Confirmado. Coleccion Dark Elegance SS26.',
            ]);

            // Create default materials
            $designerService->createDefaultMaterials($designer1, $event);

            // Create display
            DesignerDisplay::create([
                'designer_id' => $designer1->id,
                'event_id'    => $event->id,
                'status'      => 'pending',
                'notes'       => 'Pendiente envio de video y audio.',
            ]);

            // Create assistants
            DesignerAssistant::create([
                'designer_id' => $designer1->id,
                'event_id'    => $event->id,
                'full_name'   => 'Carlos Mendez',
                'document_id' => 'CC-12345678',
                'phone'       => '+1-212-555-0311',
                'email'       => 'carlos@nocturnadesign.com',
                'status'      => 'registered',
            ]);
            DesignerAssistant::create([
                'designer_id' => $designer1->id,
                'event_id'    => $event->id,
                'full_name'   => 'Laura Jimenez',
                'phone'       => '+1-212-555-0312',
                'email'       => 'laura@nocturnadesign.com',
                'status'      => 'registered',
            ]);
        }

        if ($designer2) {
            $event->designers()->attach($designer2->id, [
                'status'                => 'confirmed',
                'package_id'            => $platinumPkg?->id,
                'looks'                 => 15,
                'model_casting_enabled' => true,
                'package_price'         => 7500.00,
                'notes'                 => 'Confirmada. Coleccion Monochrome Dreams.',
            ]);

            // Create default materials
            $designerService->createDefaultMaterials($designer2, $event);

            // Create display
            DesignerDisplay::create([
                'designer_id' => $designer2->id,
                'event_id'    => $event->id,
                'status'      => 'ready',
                'notes'       => 'Video enviado via WeTransfer.',
            ]);

            // Create assistant
            DesignerAssistant::create([
                'designer_id' => $designer2->id,
                'event_id'    => $event->id,
                'full_name'   => 'Ana Gutierrez',
                'document_id' => 'PP-87654321',
                'phone'       => '+1-305-555-0411',
                'email'       => 'ana@lunawhite.com',
                'status'      => 'registered',
            ]);
        }

        // Assign designers to shows
        if ($day2 && $designer1 && $designer2) {
            $shows = $day2->shows()->orderBy('order')->get();
            if ($shows->count() >= 2) {
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
                $shows[1]->designers()->attach($designer2->id, [
                    'order'           => 1,
                    'collection_name' => 'Monochrome Dreams – Evening',
                    'status'          => 'confirmed',
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
            $castingDay->castingSlots()->where('time', '08:00')->increment('booked');
        }

        if ($model2) {
            $event->models()->attach($model2->id, [
                'participation_number'  => 2,
                'casting_time'          => '08:30',
                'casting_status'        => 'checked_in',
                'casting_checked_in_at' => now(),
                'status'                => 'confirmed',
            ]);
            $castingDay->castingSlots()->where('time', '08:30')->increment('booked');
        }

        // Modelo 3: Camila Duarte — casting 09:00
        $model3 = User::where('email', 'camila.duarte@models.com')->first();
        if ($model3) {
            $event->models()->attach($model3->id, [
                'participation_number'  => 3,
                'casting_time'          => '09:00',
                'casting_status'        => 'scheduled',
                'status'                => 'confirmed',
            ]);
            $castingDay->castingSlots()->where('time', '09:00')->increment('booked');
        }

        // Modelo 4: Aisha Williams — casting 09:30
        $model4 = User::where('email', 'aisha.w@models.com')->first();
        if ($model4) {
            $event->models()->attach($model4->id, [
                'participation_number'  => 4,
                'casting_time'          => '09:30',
                'casting_status'        => 'scheduled',
                'status'                => 'confirmed',
            ]);
            $castingDay->castingSlots()->where('time', '09:30')->increment('booked');
        }

        // Modelo 5: Natalia Petrov — casting 10:00
        $model5 = User::where('email', 'natalia.p@models.com')->first();
        if ($model5) {
            $event->models()->attach($model5->id, [
                'participation_number'  => 5,
                'casting_time'          => '10:00',
                'casting_status'        => 'scheduled',
                'status'                => 'confirmed',
            ]);
            $castingDay->castingSlots()->where('time', '10:00')->increment('booked');
        }

        // ---------------------------------------------------------------
        // Modelos adicionales 6–35
        // ---------------------------------------------------------------
        $extraModelCasting = [
            // [email, participation_number, casting_time, casting_status]
            ['daniela.ortiz@models.com',   6,  '08:00', 'checked_in'],
            ['marco.rivera@models.com',    7,  '08:00', 'scheduled'],
            ['lina.park@models.com',       8,  '08:30', 'checked_in'],
            ['emma.thompson@models.com',   9,  '08:30', 'scheduled'],
            ['valentina.cruz@models.com',  10, '09:00', 'scheduled'],
            ['zara.ahmed@models.com',      11, '09:30', 'scheduled'],
            ['mia.santos@models.com',      12, '10:00', 'checked_in'],
            ['yuki.tanaka@models.com',     13, '10:00', 'scheduled'],
            ['olivia.white@models.com',    14, '10:30', 'scheduled'],
            ['amara.diallo@models.com',    15, '10:30', 'scheduled'],
            ['andre.baptiste@models.com',  16, '11:00', 'scheduled'],
            ['chloe.martin@models.com',    17, '11:00', 'scheduled'],
            ['priya.sharma@models.com',    18, '11:30', 'scheduled'],
            ['adriana.lopez@models.com',   19, '12:00', 'scheduled'],
            ['naomi.scott@models.com',     20, '12:00', 'scheduled'],
            ['sara.kim@models.com',        21, '12:30', 'scheduled'],
            ['bianca.rossi@models.com',    22, '13:00', 'scheduled'],
            ['fatima.hassan@models.com',   23, '13:00', 'scheduled'],
            ['kai.nakamura@models.com',    24, '13:30', 'scheduled'],
            ['elena.volkov@models.com',    25, '14:00', 'scheduled'],
            ['catalina.reyes@models.com',  26, '14:00', 'scheduled'],
            ['nia.brooks@models.com',      27, '14:30', 'scheduled'],
            ['mei.lin@models.com',         28, '15:00', 'scheduled'],
            ['jordan.ellis@models.com',    29, '15:00', 'scheduled'],
            ['gabriela.mendez@models.com', 30, '15:30', 'scheduled'],
            ['sienna.james@models.com',    31, '16:00', 'scheduled'],
            ['leila.nazari@models.com',    32, '16:30', 'scheduled'],
            ['harper.davis@models.com',    33, '17:00', 'scheduled'],
            ['xiomara.vega@models.com',    34, '17:30', 'scheduled'],
            ['tiana.moore@models.com',     35, '18:00', 'scheduled'],
        ];

        foreach ($extraModelCasting as [$email, $number, $time, $castingStatus]) {
            $extraModel = User::where('email', $email)->first();
            if ($extraModel) {
                $event->models()->attach($extraModel->id, [
                    'participation_number'  => $number,
                    'casting_time'          => $time,
                    'casting_status'        => $castingStatus,
                    'casting_checked_in_at' => $castingStatus === 'checked_in' ? now() : null,
                    'status'                => 'confirmed',
                ]);
                $castingDay->castingSlots()->where('time', $time)->increment('booked');
            }
        }
    }
}
