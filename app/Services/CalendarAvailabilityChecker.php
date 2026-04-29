<?php

namespace App\Services;

use App\Models\CalendarActivity;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Centraliza la detección de conflictos de calendario para un user dado en una
 * fecha/hora. Usado por:
 *   - GET /admin/{area}/calendar/availability  (warning visual)
 *   - Endpoints de creación/edición de actividades (hard-block)
 *
 * Regla: una actividad pendiente del user dentro de ±30 min del horario pedido
 * cuenta como conflicto. Atraviesa áreas (sales lead activities + sponsorship
 * lead activities + personal calendar entries) — la disponibilidad de un user
 * es única, no por área.
 */
class CalendarAvailabilityChecker
{
    public const WINDOW_MINUTES = 30;

    /**
     * Devuelve la lista de actividades en conflicto en el rango [$from, $to]
     * para el $userId dado. $excludes permite ignorar la propia actividad cuando
     * se está editando.
     *
     *   $excludes = [
     *     'sponsorship_lead' => 12,  // ignorar Sponsorship\LeadActivity id 12
     *     'sales_lead'       => 34,  // ignorar LeadActivity id 34
     *     'personal'         => 56,  // ignorar CalendarActivity id 56
     *   ]
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getConflicts(int $userId, Carbon $from, Carbon $to, array $excludes = []): Collection
    {
        $sponsorshipLead = \App\Models\Sponsorship\LeadActivity::query()
            ->where('assigned_to_user_id', $userId)
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [$from, $to])
            ->when(!empty($excludes['sponsorship_lead']), fn($q) => $q->where('id', '!=', $excludes['sponsorship_lead']))
            ->get(['id', 'title', 'type', 'scheduled_at'])
            ->map(fn($a) => [
                'id'     => $a->id,
                'source' => 'lead',
                'area'   => 'sponsorship',
                'title'  => $a->title,
                'type'   => $a->type,
                'start'  => $a->scheduled_at?->toIso8601String(),
            ]);

        $salesLead = \App\Models\LeadActivity::query()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [$from, $to])
            ->when(!empty($excludes['sales_lead']), fn($q) => $q->where('id', '!=', $excludes['sales_lead']))
            ->get(['id', 'title', 'type', 'scheduled_at'])
            ->map(fn($a) => [
                'id'     => $a->id,
                'source' => 'lead',
                'area'   => 'sales',
                'title'  => $a->title,
                'type'   => $a->type,
                'start'  => $a->scheduled_at?->toIso8601String(),
            ]);

        $personal = CalendarActivity::query()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [$from, $to])
            ->when(!empty($excludes['personal']), fn($q) => $q->where('id', '!=', $excludes['personal']))
            ->get(['id', 'title', 'type', 'scheduled_at'])
            ->map(fn($a) => [
                'id'     => $a->id,
                'source' => 'personal',
                'area'   => null,
                'title'  => $a->title,
                'type'   => $a->type,
                'start'  => $a->scheduled_at?->toIso8601String(),
            ]);

        return $sponsorshipLead->concat($salesLead)->concat($personal)->values();
    }

    /**
     * Lanza ValidationException si el user ya tiene actividades pendientes en
     * ±WINDOW_MINUTES alrededor de $scheduledAt. Idempotente: si $scheduledAt
     * es null, no hace nada.
     */
    public function assertNoConflict(?int $userId, ?string $scheduledAt, array $excludes = []): void
    {
        if (!$userId || !$scheduledAt) return;

        $center = Carbon::parse($scheduledAt);
        $from = $center->copy()->subMinutes(self::WINDOW_MINUTES);
        $to   = $center->copy()->addMinutes(self::WINDOW_MINUTES);

        $conflicts = $this->getConflicts($userId, $from, $to, $excludes);
        if ($conflicts->isEmpty()) return;

        $first = $conflicts->first();
        $startLabel = Carbon::parse($first['start'])->format('M j, g:i A');
        throw ValidationException::withMessages([
            'scheduled_at' => "The user is already booked at {$startLabel} ({$first['title']}). Pick another time.",
        ]);
    }
}
