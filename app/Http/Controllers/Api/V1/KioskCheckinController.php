<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\EventDay;
use App\Models\EventPass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskCheckinController extends Controller
{
    public function checkin(Request $request): JsonResponse
    {
        $request->validate(['qr_code' => 'required|string']);

        $pass = EventPass::where('qr_code', $request->qr_code)
            ->where('status', 'active')
            ->with(['user', 'event'])
            ->first();

        if (!$pass) {
            return response()->json([
                'success' => false,
                'message' => 'Pase inválido o inactivo.',
            ], 404);
        }

        $user    = $pass->user;
        $eventId = $pass->event_id;

        // Buscar el día de evento de hoy
        $eventDay = EventDay::where('event_id', $eventId)
            ->whereDate('date', today())
            ->first();

        if (!$eventDay) {
            return response()->json([
                'success' => false,
                'message' => 'No hay día de evento activo para hoy.',
            ], 422);
        }

        $needsEntryExit = Checkin::needsEntryExit($user);

        if ($needsEntryExit) {
            $entry = Checkin::where('user_id', $user->id)
                ->where('event_day_id', $eventDay->id)
                ->where('type', 'entry')
                ->first();

            $exit = Checkin::where('user_id', $user->id)
                ->where('event_day_id', $eventDay->id)
                ->where('type', 'exit')
                ->first();

            if ($entry && $exit) {
                return response()->json([
                    'success' => false,
                    'message' => "{$user->first_name} ya completó su entrada y salida de hoy.",
                ], 422);
            }

            $type = $entry ? 'exit' : 'entry';
        } else {
            $existing = Checkin::where('user_id', $user->id)
                ->where('event_day_id', $eventDay->id)
                ->where('type', 'single')
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => "{$user->first_name} ya tiene marcación para hoy.",
                ], 422);
            }

            $type = 'single';
        }

        $checkin = Checkin::create([
            'user_id'      => $user->id,
            'event_id'     => $eventId,
            'event_day_id' => $eventDay->id,
            'type'         => $type,
            'checked_at'   => now(),
            'method'       => 'kiosk',
        ]);

        // Área (solo volunteers/staff)
        $area = null;
        if (in_array($user->role, ['volunteer', 'staff'])) {
            $area = \DB::table('event_staff')
                ->where('user_id', $user->id)
                ->where('event_id', $eventId)
                ->value('area');
        }

        $typeLabel = ['entry' => 'Entrada', 'exit' => 'Salida', 'single' => 'Asistencia'][$type];

        return response()->json([
            'success'    => true,
            'type'       => $type,
            'type_label' => $typeLabel,
            'name'       => $user->full_name,
            'role'       => $user->role,
            'area'       => $area,
            'event'      => $pass->event->name,
            'day'        => $eventDay->label,
            'checked_at' => $checkin->checked_at->format('H:i'),
        ]);
    }
}
