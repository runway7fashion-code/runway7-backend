<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventPass;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * Pases del usuario autenticado (modelos, diseñadores, staff, etc.)
     */
    public function myPasses(Request $request): JsonResponse
    {
        $user = $request->user();

        $passes = EventPass::where('user_id', $user->id)
            ->with('event:id,name,city,venue,start_date,end_date')
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $passes->map(function ($pass) {
            return [
                'id' => $pass->id,
                'qr_code' => $pass->qr_code,
                'pass_type' => $pass->pass_type,
                'pass_type_label' => $pass->passTypeLabel(),
                'holder_name' => $pass->holder_name,
                'valid_days' => $pass->valid_days,
                'status' => $pass->status,
                'checked_in_at' => $pass->checked_in_at?->toIso8601String(),
                'event' => $pass->event ? [
                    'id' => $pass->event->id,
                    'name' => $pass->event->name,
                    'city' => $pass->event->city,
                    'venue' => $pass->event->venue,
                ] : null,
            ];
        });

        return response()->json(['passes' => $data]);
    }

    /**
     * Tickets comprados por el usuario (por email).
     */
    public function myTickets(Request $request): JsonResponse
    {
        $user = $request->user();

        $tickets = Ticket::where('buyer_email', $user->email)
            ->with('ticketType.eventDay.event:id,name,city')
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $tickets->map(function ($ticket) {
            $eventDay = $ticket->ticketType?->eventDay;
            $event = $eventDay?->event;

            return [
                'id' => $ticket->id,
                'qr_code' => $ticket->qr_code,
                'buyer_name' => $ticket->buyer_full_name,
                'status' => $ticket->status,
                'ticket_type' => $ticket->ticketType?->name,
                'zone' => $ticket->ticketType?->zone,
                'event' => $event ? [
                    'id' => $event->id,
                    'name' => $event->name,
                ] : null,
                'day' => $eventDay ? [
                    'date' => $eventDay->date->format('Y-m-d'),
                    'label' => $eventDay->label,
                ] : null,
                'first_check_in_at' => $ticket->first_check_in_at?->toIso8601String(),
            ];
        });

        return response()->json(['tickets' => $data]);
    }

    /**
     * Escanear QR para check-in (staff/admin).
     * Acepta códigos de EventPass (PASS-XXXXXX) o Ticket.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $qrCode = $request->input('qr_code');

        // Intentar como EventPass primero
        if (str_starts_with($qrCode, 'PASS-')) {
            return $this->checkInPass($qrCode);
        }

        // Intentar como Ticket
        return $this->checkInTicket($qrCode);
    }

    private function checkInPass(string $qrCode): JsonResponse
    {
        $pass = EventPass::where('qr_code', $qrCode)
            ->with(['event:id,name', 'user:id,first_name,last_name,role'])
            ->first();

        if (!$pass) {
            return response()->json(['message' => 'Pase no encontrado.', 'valid' => false], 404);
        }

        if ($pass->status === 'cancelled') {
            return response()->json(['message' => 'Pase cancelado.', 'valid' => false], 422);
        }

        // Registrar check-in
        $history = $pass->check_in_history ?? [];
        $history[] = ['checked_in_at' => now()->toIso8601String()];

        $pass->update([
            'checked_in_at' => now(),
            'check_in_history' => $history,
            'status' => 'used',
        ]);

        return response()->json([
            'valid' => true,
            'type' => 'pass',
            'message' => 'Check-in exitoso.',
            'data' => [
                'holder_name' => $pass->holder_name ?? $pass->user?->first_name . ' ' . $pass->user?->last_name,
                'pass_type' => $pass->passTypeLabel(),
                'event' => $pass->event?->name,
                'previous_check_ins' => count($history) - 1,
            ],
        ]);
    }

    private function checkInTicket(string $qrCode): JsonResponse
    {
        $ticket = Ticket::where('qr_code', $qrCode)
            ->with('ticketType.eventDay.event:id,name')
            ->first();

        if (!$ticket) {
            return response()->json(['message' => 'Ticket no encontrado.', 'valid' => false], 404);
        }

        if ($ticket->status === 'cancelled' || $ticket->status === 'refunded') {
            return response()->json(['message' => 'Ticket ' . $ticket->status . '.', 'valid' => false], 422);
        }

        // Registrar check-in
        $checkTimes = $ticket->check_times ?? [];
        $checkTimes[] = now()->toIso8601String();

        $updateData = [
            'check_times' => $checkTimes,
            'status' => 'checked_in',
        ];

        if (!$ticket->first_check_in_at) {
            $updateData['first_check_in_at'] = now();
        }

        $ticket->update($updateData);

        return response()->json([
            'valid' => true,
            'type' => 'ticket',
            'message' => 'Check-in exitoso.',
            'data' => [
                'buyer_name' => $ticket->buyer_full_name,
                'ticket_type' => $ticket->ticketType?->name,
                'zone' => $ticket->ticketType?->zone,
                'event' => $ticket->ticketType?->eventDay?->event?->name,
                'previous_check_ins' => count($checkTimes) - 1,
            ],
        ]);
    }
}
