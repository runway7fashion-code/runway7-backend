<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\VolunteerSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VolunteerCertificateController extends Controller
{
    /**
     * Lista los eventos donde el voluntario tiene certificado ganado.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Solo aplica a voluntarios
        if (!in_array($user->role, ['volunteer', 'staff'])) {
            return response()->json([]);
        }

        // Eventos en los que participó
        $eventIds = VolunteerSchedule::where('user_id', $user->id)
            ->distinct()
            ->pluck('event_id');

        $results = [];

        foreach ($eventIds as $eventId) {
            $event = Event::find($eventId);
            if (!$event) continue;

            $eligible = $this->checkEligibility($user->id, $eventId);

            $results[] = [
                'event_id'   => $event->id,
                'event_name' => $event->name,
                'eligible'   => $eligible,
            ];
        }

        return response()->json($results);
    }

    /**
     * Descarga el certificado PDF para un evento.
     */
    public function download(Request $request, Event $event)
    {
        $user = $request->user();

        if (!$this->checkEligibility($user->id, $event->id)) {
            return response()->json(['error' => 'You have not completed all assigned days for this event.'], 403);
        }

        $pdf = Pdf::loadView('pdf.volunteer_certificate', [
            'volunteer' => $user,
            'event'     => $event,
        ])->setPaper('letter', 'landscape');

        $filename = 'certificate_' . Str::slug($user->first_name . '_' . $user->last_name) . '_' . Str::slug($event->name) . '.pdf';

        return $pdf->download($filename);
    }

    private function checkEligibility(int $userId, int $eventId): bool
    {
        $scheduledDayIds = VolunteerSchedule::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->pluck('event_day_id');

        if ($scheduledDayIds->isEmpty()) return false;

        $attendedDayIds = Checkin::where('user_id', $userId)
            ->whereIn('event_day_id', $scheduledDayIds)
            ->pluck('event_day_id')
            ->unique();

        return $scheduledDayIds->diff($attendedDayIds)->isEmpty();
    }
}
