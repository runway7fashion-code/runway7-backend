<?php

namespace Database\Seeders;

use App\Models\DesignerContactEmail;
use App\Models\SupportCase;
use App\Models\User;
use App\Models\Event;
use Illuminate\Database\Seeder;

class SupportCaseSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::where('slug', 'nyfw-september-2026')->first();
        if (!$event) return;

        $designer1 = User::where('email', 'ale@nocturnadesign.com')->first();
        $designer2 = User::where('email', 'val@lunawhite.com')->first();
        $accounting = User::where('email', 'accounting@runway7.com')->first();

        if (!$designer1 || !$designer2 || !$accounting) return;

        // Save contact emails
        DesignerContactEmail::firstOrCreate(
            ['designer_id' => $designer1->id, 'email' => 'alejandro.asistente@gmail.com'],
            ['label' => 'Asistente'],
        );

        DesignerContactEmail::firstOrCreate(
            ['designer_id' => $designer2->id, 'email' => 'valentina@lunawhite.com'],
            ['label' => 'Personal'],
        );

        // Case 1: CASO-0001
        $case1 = SupportCase::create([
            'case_number' => 'CASO-0001',
            'designer_id' => $designer1->id,
            'event_id' => $event->id,
            'channel' => 'email',
            'case_type' => 'payment',
            'contact_email' => 'alejandro.asistente@gmail.com',
            'claim_date' => '2026-02-15',
            'status' => 'in_progress',
            'created_by' => $accounting->id,
        ]);

        $msg1 = $case1->messages()->create([
            'sender_type' => 'designer',
            'message' => 'Hola, realicé un pago de $500 por Zelle el día 10 de febrero pero no se ha reflejado en mi cuenta. Adjunto captura del comprobante.',
            'message_date' => '2026-02-15',
        ]);

        $msg2 = $case1->messages()->create([
            'sender_type' => 'team',
            'team_member_id' => $accounting->id,
            'message' => 'Hola Alejandro, estamos verificando el pago con el departamento financiero. Te daremos respuesta en 24 horas.',
            'message_date' => '2026-02-16',
        ]);

        $msg3 = $case1->messages()->create([
            'sender_type' => 'designer',
            'message' => 'Gracias, quedo atento.',
            'message_date' => '2026-02-16',
        ]);

        // Case 2: CASO-0002
        $case2 = SupportCase::create([
            'case_number' => 'CASO-0002',
            'designer_id' => $designer2->id,
            'event_id' => $event->id,
            'channel' => 'whatsapp',
            'case_type' => 'complaint',
            'contact_email' => 'valentina@lunawhite.com',
            'claim_date' => '2026-02-20',
            'status' => 'open',
            'created_by' => $accounting->id,
        ]);

        $case2->messages()->create([
            'sender_type' => 'designer',
            'message' => 'Necesito cambiar la fecha de mi cuota del 1 de marzo al 15 de marzo. ¿Es posible?',
            'message_date' => '2026-02-20',
        ]);
    }
}
