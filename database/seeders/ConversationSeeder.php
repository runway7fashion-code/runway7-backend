<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Show;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        $model1    = User::where('email', 'sofia.rivera@models.com')->first();
        $model2    = User::where('email', 'isabella.chen@models.com')->first();
        $designer1 = User::where('email', 'ale@nocturnadesign.com')->first();
        $designer2 = User::where('email', 'val@lunawhite.com')->first();

        if (! $model1 || ! $designer1 || ! $designer2) {
            return;
        }

        // Find shows where designers are assigned
        $designer1Shows = $designer1->designedShows()->get();
        $designer2Shows = $designer2->designedShows()->get();

        $show1 = $designer1Shows->first();
        $show2 = $designer2Shows->first();

        if (! $show1 || ! $show2) {
            return;
        }

        // Conversation 1: Sofia Rivera <-> Alejandro Vasquez (Nocturna Design)
        $conv1 = Conversation::create([
            'model_id'        => $model1->id,
            'designer_id'     => $designer1->id,
            'show_id'         => $show1->id,
            'status'          => 'active',
            'last_message_at' => now()->subMinutes(15),
        ]);

        Message::create([
            'conversation_id' => $conv1->id,
            'sender_id'       => $designer1->id,
            'body'            => 'Hola Sofia, bienvenida al show Dark Elegance SS26. Necesitamos coordinar el fitting para la semana previa al evento.',
            'type'            => 'text',
            'is_read'         => true,
            'read_at'         => now()->subHours(2),
            'created_at'      => now()->subHours(3),
        ]);

        Message::create([
            'conversation_id' => $conv1->id,
            'sender_id'       => $model1->id,
            'body'            => 'Hola Alejandro! Gracias, estoy muy emocionada. Estoy disponible cualquier dia de la semana. Que horario te funciona mejor?',
            'type'            => 'text',
            'is_read'         => true,
            'read_at'         => now()->subHours(1),
            'created_at'      => now()->subHours(2),
        ]);

        Message::create([
            'conversation_id' => $conv1->id,
            'sender_id'       => $designer1->id,
            'body'            => 'Perfecto, te agendo para el martes a las 10am en el atelier. Te envio la direccion por aqui.',
            'type'            => 'text',
            'is_read'         => false,
            'created_at'      => now()->subMinutes(15),
        ]);

        // Conversation 2: Sofia Rivera <-> Valentina Morales (Luna White)
        $conv2 = Conversation::create([
            'model_id'        => $model1->id,
            'designer_id'     => $designer2->id,
            'show_id'         => $show2->id,
            'status'          => 'active',
            'last_message_at' => now()->subHours(1),
        ]);

        Message::create([
            'conversation_id' => $conv2->id,
            'sender_id'       => $designer2->id,
            'body'            => 'Hola Sofia! Soy Valentina de Luna White. Te seleccionamos para la coleccion Monochrome Dreams. Sera un placer trabajar contigo.',
            'type'            => 'text',
            'is_read'         => true,
            'read_at'         => now()->subHours(4),
            'created_at'      => now()->subHours(5),
        ]);

        Message::create([
            'conversation_id' => $conv2->id,
            'sender_id'       => $model1->id,
            'body'            => 'Hola Valentina! Muchas gracias por la oportunidad. Me encanta la estetica de Luna White. Cuando seria el primer ensayo?',
            'type'            => 'text',
            'is_read'         => true,
            'read_at'         => now()->subHours(2),
            'created_at'      => now()->subHours(3),
        ]);

        Message::create([
            'conversation_id' => $conv2->id,
            'sender_id'       => $designer2->id,
            'body'            => 'El primer ensayo sera el lunes 1 de septiembre. Te envio todos los detalles por email tambien. Recuerda traer zapatos de tacon nude.',
            'type'            => 'text',
            'is_read'         => true,
            'read_at'         => now()->subHours(1),
            'created_at'      => now()->subHours(1),
        ]);
    }
}
