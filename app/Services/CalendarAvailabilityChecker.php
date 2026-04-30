<?php

namespace App\Services;

use App\Models\CalendarActivity;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Detección de conflictos de calendario por solapamiento real de rangos.
 *
 *  - Una actividad sin `ends_at` es un punto en el tiempo: no genera bloqueo.
 *  - Una actividad con `ends_at` define el rango [scheduled_at, ends_at] en el
 *    cual el user está ocupado. Cualquier otra actividad que se solape se
 *    bloquea (back-to-back permitido — fin == inicio no choca).
 *
 * Regla de overlap entre A y B (donde end = scheduled_at si no hay ends_at):
 *   A.start < B.end  AND  B.start < A.end
 *
 * Atraviesa las 3 fuentes (sponsorship lead, sales lead, personal calendar) ya
 * que la disponibilidad de un user es única.
 */
class CalendarAvailabilityChecker
{
    /**
     * Devuelve actividades del user que se solapan con el rango [start, end].
     * `end` puede ser igual a `start` (actividad nueva sin ends_at).
     *
     * `$excludes` permite ignorar la propia actividad cuando se edita:
     *   ['sponsorship_lead' => 12, 'sales_lead' => 34, 'personal' => 56]
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getConflicts(int $userId, Carbon $start, Carbon $end, array $excludes = []): Collection
    {
        $sponsorshipLead = $this->queryRangeOverlap(
            \App\Models\Sponsorship\LeadActivity::query()
                ->where('assigned_to_user_id', $userId)
                ->where('status', 'pending'),
            $start,
            $end,
            !empty($excludes['sponsorship_lead']) ? (int) $excludes['sponsorship_lead'] : null,
        )->get(['id', 'title', 'type', 'scheduled_at', 'ends_at'])
         ->map(fn($a) => $this->mapEvent($a, 'lead', 'sponsorship'));

        $salesLead = $this->queryRangeOverlap(
            \App\Models\LeadActivity::query()
                ->where('user_id', $userId)
                ->where('status', 'pending'),
            $start,
            $end,
            !empty($excludes['sales_lead']) ? (int) $excludes['sales_lead'] : null,
        )->get(['id', 'title', 'type', 'scheduled_at', 'ends_at'])
         ->map(fn($a) => $this->mapEvent($a, 'lead', 'sales'));

        $personal = $this->queryRangeOverlap(
            CalendarActivity::query()
                ->where('user_id', $userId)
                ->where('status', 'pending'),
            $start,
            $end,
            !empty($excludes['personal']) ? (int) $excludes['personal'] : null,
        )->get(['id', 'title', 'type', 'scheduled_at', 'ends_at'])
         ->map(fn($a) => $this->mapEvent($a, 'personal', null));

        return $sponsorshipLead->concat($salesLead)->concat($personal)->values();
    }

    /**
     * Lanza ValidationException si hay overlap. No-op si no hay scheduled_at.
     * Si `$endsAt` es null se trata como punto en el tiempo (sin bloqueo
     * cuando ambas son punto).
     */
    public function assertNoConflict(?int $userId, ?string $scheduledAt, ?string $endsAt = null, array $excludes = []): void
    {
        if (!$userId || !$scheduledAt) return;

        $start = Carbon::parse($scheduledAt);
        $end = $endsAt ? Carbon::parse($endsAt) : $start->copy();

        // Si no hay ends_at, NO hay bloqueo: un punto puede coexistir con otro
        // punto y con cualquier rango (los rangos se chequean en el otro lado
        // cuando se edita esa actividad rangueada). Lo único que puede chocar
        // es si la nueva cae DENTRO de un rango existente — eso lo cubre el
        // query igual.
        $conflicts = $this->getConflicts($userId, $start, $end, $excludes);
        if ($conflicts->isEmpty()) return;

        // Filtramos: una actividad existente sin ends_at NO bloquea a la nueva
        // (regla del usuario — los puntos no chocan entre sí).
        // Solo bloquea si la EXISTENTE tiene rango, o si la NUEVA tiene rango
        // y un punto existente cae dentro.
        $newHasRange = $endsAt !== null;
        $real = $conflicts->filter(function ($c) use ($newHasRange) {
            $existingHasRange = !empty($c['ends_at']);
            return $existingHasRange || $newHasRange;
        });
        if ($real->isEmpty()) return;

        $first = $real->first();
        $startLabel = Carbon::parse($first['start'])->setTimezone('America/Lima')->format('M j, g:i A');
        $endLabel = !empty($first['ends_at'])
            ? Carbon::parse($first['ends_at'])->setTimezone('America/Lima')->format('g:i A')
            : null;
        $window = $endLabel ? "{$startLabel} – {$endLabel}" : $startLabel;
        throw ValidationException::withMessages([
            'scheduled_at' => "The user is already booked at {$window} ({$first['title']}). Pick another time.",
        ]);
    }

    /**
     * Aplica el filtro de overlap a un query base.
     * Existing.end = ends_at si no es null, sino scheduled_at (punto).
     * Nuevo rango: [start, end] (end = start si la nueva es punto).
     * Overlap iff: existing.start < new.end AND new.start < existing.end.
     */
    private function queryRangeOverlap($query, Carbon $start, Carbon $end, ?int $excludeId)
    {
        $endStr   = $end->toDateTimeString();
        $startStr = $start->toDateTimeString();
        // existing.start < new.end
        $query->where('scheduled_at', '<', $endStr);
        // new.start < existing.end (= ends_at coalesced to scheduled_at)
        $query->whereRaw('? < COALESCE(ends_at, scheduled_at)', [$startStr]);
        if ($excludeId) $query->where('id', '!=', $excludeId);
        return $query;
    }

    private function mapEvent($activity, string $source, ?string $area): array
    {
        return [
            'id'        => $activity->id,
            'source'    => $source,
            'area'      => $area,
            'title'     => $activity->title,
            'type'      => $activity->type,
            'start'     => $activity->scheduled_at?->toIso8601String(),
            'ends_at'   => $activity->ends_at?->toIso8601String(),
        ];
    }
}
