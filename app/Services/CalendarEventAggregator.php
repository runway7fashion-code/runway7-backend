<?php

namespace App\Services;

use App\Models\CalendarActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Centraliza la construcción del feed del calendario por área.
 *
 * Devuelve eventos con shape uniforme — independiente de qué tabla sirva el dato:
 *   - lead activities (App\Models\LeadActivity      para sales,
 *                       App\Models\Sponsorship\LeadActivity para sponsorship)
 *   - personal activities (App\Models\CalendarActivity con `area` matching)
 *
 * Cada evento lleva `area` explícita para que el frontend pinte la procedencia.
 * Se respeta la visibilidad por leader/asesor del área en cuestión: un asesor solo
 * ve lo suyo + lo de los líderes (incluyendo cross-area).
 */
class CalendarEventAggregator
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fetchForArea(string $area, Request $request, User $user): Collection
    {
        if (!in_array($area, ['sales', 'sponsorship'], true)) {
            return collect();
        }
        // Si el user no tiene acceso a esta área, no devolvemos nada (defensivo).
        if (!$user->canManageArea($area)) {
            return collect();
        }

        $visibleUserIds = $this->visibleUserIds($area, $user);

        $leadEvents = $area === 'sales'
            ? $this->fetchSalesLeadEvents($request, $user, $visibleUserIds)
            : $this->fetchSponsorshipLeadEvents($request, $user, $visibleUserIds);

        $personalEvents = $this->fetchPersonalEvents($area, $request, $user, $visibleUserIds);

        // Visibilidad cross-area de los multi-área (Christina): aunque el viewer
        // no tenga acceso a la otra área, ve las actividades de los multi-área
        // para coordinar disponibilidad. El backend además sigue chequeando
        // disponibilidad cruzada al agendar (CalendarAvailabilityChecker), pero
        // este merge da visibilidad transparente en el calendario.
        $crossLeadEvents = $this->fetchCrossAreaLeadEvents($area, $request, $user);

        return $leadEvents->concat($personalEvents)->concat($crossLeadEvents)->values();
    }

    /**
     * Trae actividades de la OTRA área pero solo para users con extra_areas no
     * vacío (multi-área, p.ej. Christina). Si no hay multi-área, devuelve vacío.
     */
    private function fetchCrossAreaLeadEvents(string $area, Request $request, User $user): Collection
    {
        $crossAreaIds = User::crossAreaIds();
        if (empty($crossAreaIds)) return collect();

        $otherArea = $area === 'sales' ? 'sponsorship' : 'sales';

        return $otherArea === 'sales'
            ? $this->fetchSalesLeadEvents($request, $user, $crossAreaIds)
            : $this->fetchSponsorshipLeadEvents($request, $user, $crossAreaIds);
    }

    /**
     * Set de user_ids cuyas actividades el viewer puede ver dentro de $area.
     *  - admin               → null  (sin filtro)
     *  - leader del área     → todo el equipo del área (incluye cross-area)
     *  - asesor              → él mismo + users multi-área (p.ej. Christina)
     */
    private function visibleUserIds(string $area, User $user): ?array
    {
        if ($user->role === 'admin') {
            return null;
        }
        if ($user->isLeaderOf($area)) {
            return User::teamMembers($area)->pluck('id')->all();
        }
        return array_values(array_unique(array_merge([$user->id], User::crossAreaIds())));
    }

    private function fetchSalesLeadEvents(Request $request, User $user, ?array $visibleUserIds): Collection
    {
        $q = \App\Models\LeadActivity::whereNotNull('scheduled_at')
            ->with(['lead:id,first_name,last_name,company_name', 'user:id,first_name,last_name']);

        if ($visibleUserIds !== null) {
            $q->whereIn('user_id', $visibleUserIds);
        }
        if ($request->filled('advisor')) {
            $q->where('user_id', $request->advisor);
        }
        if ($request->filled('start') && $request->filled('end')) {
            $q->whereBetween('scheduled_at', [$request->start, $request->end]);
        }

        return $q->get()->map(fn($a) => [
            'id'          => $a->id,
            'source'      => 'lead',
            'area'        => 'sales',
            'title'       => $a->title,
            'start'       => $a->scheduled_at?->toIso8601String(),
            'ends_at'     => $a->ends_at?->toIso8601String(),
            'type'        => $a->type,
            'status'      => $a->status,
            'lead_id'     => $a->lead_id,
            'lead_name'   => $a->lead?->full_name,
            'company'     => $a->lead?->company_name,
            'advisor'     => $a->user ? $a->user->first_name . ' ' . $a->user->last_name : null,
            'advisor_id'  => $a->user_id,
            'description' => $a->description,
        ]);
    }

    private function fetchSponsorshipLeadEvents(Request $request, User $user, ?array $visibleUserIds): Collection
    {
        $q = \App\Models\Sponsorship\LeadActivity::whereNotNull('scheduled_at')
            ->with([
                'lead:id,first_name,last_name,company_id',
                'lead.company:id,name',
                'assignedTo:id,first_name,last_name',
                'creator:id,first_name,last_name',
            ]);

        if ($visibleUserIds !== null) {
            $q->where(function ($qq) use ($user, $visibleUserIds) {
                $qq->whereIn('assigned_to_user_id', $visibleUserIds)
                   ->orWhere('created_by_user_id', $user->id);
            });
        }
        if ($request->filled('advisor')) {
            $q->where('assigned_to_user_id', $request->advisor);
        }
        if ($request->filled('start') && $request->filled('end')) {
            $q->whereBetween('scheduled_at', [$request->start, $request->end]);
        }

        return $q->get()->map(fn($a) => [
            'id'          => $a->id,
            'source'      => 'lead',
            'area'        => 'sponsorship',
            'title'       => $a->title,
            'start'       => $a->scheduled_at?->toIso8601String(),
            'ends_at'     => $a->ends_at?->toIso8601String(),
            'type'        => $a->type,
            'status'      => $a->status,
            'is_contract' => $a->is_contract,
            'lead_id'     => $a->lead_id,
            'lead_name'   => $a->lead ? "{$a->lead->first_name} {$a->lead->last_name}" : null,
            'company'     => $a->lead?->company?->name,
            'advisor'     => $a->assignedTo ? "{$a->assignedTo->first_name} {$a->assignedTo->last_name}" : null,
            'advisor_id'  => $a->assigned_to_user_id,
            'creator'     => $a->creator ? "{$a->creator->first_name} {$a->creator->last_name}" : null,
            'description' => $a->description,
        ]);
    }

    private function fetchPersonalEvents(string $area, Request $request, User $user, ?array $visibleUserIds): Collection
    {
        // Una actividad personal de un user (sin lead asociado) bloquea su calendario
        // independientemente del área. Incluimos las del área pedida + las globales
        // (area null) para que un sponsorship asesor agendando con Christina vea
        // también las personales que Christina creó desde Sales (y viceversa).
        $q = CalendarActivity::query()
            ->where(function ($qq) use ($area) {
                $qq->where('area', $area)->orWhereNull('area');
            })
            ->whereNotNull('scheduled_at')
            ->with(['user:id,first_name,last_name', 'creator:id,first_name,last_name']);

        if ($visibleUserIds !== null) {
            $q->where(function ($qq) use ($user, $visibleUserIds) {
                $qq->whereIn('user_id', $visibleUserIds)
                   ->orWhere('created_by_user_id', $user->id);
            });
        }
        if ($request->filled('advisor')) {
            $q->where('user_id', $request->advisor);
        }
        if ($request->filled('start') && $request->filled('end')) {
            $q->whereBetween('scheduled_at', [$request->start, $request->end]);
        }

        return $q->get()->map(fn($a) => [
            'id'          => $a->id,
            'source'      => 'personal',
            'area'        => $area,
            'title'       => $a->title,
            'start'       => $a->scheduled_at?->toIso8601String(),
            'ends_at'     => $a->ends_at?->toIso8601String(),
            'type'        => $a->type,
            'status'      => $a->status,
            'is_contract' => false,
            'lead_id'     => null,
            'lead_name'   => null,
            'company'     => null,
            'advisor'     => $a->user ? "{$a->user->first_name} {$a->user->last_name}" : null,
            'advisor_id'  => $a->user_id,
            'creator'     => $a->creator ? "{$a->creator->first_name} {$a->creator->last_name}" : null,
            'description' => $a->description,
        ]);
    }
}
