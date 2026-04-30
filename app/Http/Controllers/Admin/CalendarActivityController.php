<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD for personal calendar entries (not tied to a lead). Same table backs
 * both Sales and Sponsorship calendars; `area` is required at create time so
 * each panel can filter its events.
 *
 * Visibility rules:
 *  - Admin: everything.
 *  - Leader of $area: anything tagged with that area.
 *  - Otherwise (advisor): only entries where they are the assigned user
 *    OR the assigned user is a leader of $area (so they can see and
 *    coordinate with leader's calendars, including cross-area like Christina).
 *
 * The aggregated calendar listing happens in the area-specific
 * Lead/Sales controller's calendarEvents method (UNION there).
 */
class CalendarActivityController extends Controller
{
    public function store(Request $request, \App\Services\CalendarAvailabilityChecker $checker)
    {
        $validated = $this->validatePayload($request, true);
        $user = auth()->user();

        // Si viene area, validamos que el user pueda manejarla. Si no viene
        // (caso normal a partir de ahora), la actividad personal queda global —
        // bloquea disponibilidad sin asociarse a un área.
        if (!empty($validated['area'])) {
            $this->authorizeArea($validated['area']);
        }

        // Hard-block por overlap real (rangos). Sin ends_at NO bloquea.
        $checker->assertNoConflict(
            $validated['user_id'] ?? $user->id,
            $validated['scheduled_at'] ?? null,
            $validated['ends_at'] ?? null,
        );

        $activity = CalendarActivity::create([
            'user_id'            => $validated['user_id'] ?? $user->id,
            'created_by_user_id' => $user->id,
            'area'               => $validated['area'] ?? null,
            'type'               => $validated['type'],
            'title'              => $validated['title'],
            'description'        => $validated['description'] ?? null,
            'scheduled_at'       => $validated['scheduled_at'] ?? null,
            'ends_at'            => $validated['ends_at'] ?? null,
            'status'             => 'pending',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'activity' => $activity], 201);
        }
        return back()->with('success', 'Activity created.');
    }

    public function update(Request $request, CalendarActivity $calendarActivity, \App\Services\CalendarAvailabilityChecker $checker)
    {
        $this->authorizeEdit($calendarActivity);

        $validated = $this->validatePayload($request, false);

        // Hard-block: si cambia el horario o el usuario destino, validar que el
        // nuevo slot esté libre (excluyendo esta misma actividad).
        $targetUserId  = $validated['user_id']      ?? $calendarActivity->user_id;
        $targetSchedAt = array_key_exists('scheduled_at', $validated)
            ? $validated['scheduled_at']
            : $calendarActivity->scheduled_at?->toIso8601String();
        $targetEndsAt = array_key_exists('ends_at', $validated)
            ? $validated['ends_at']
            : $calendarActivity->ends_at?->toIso8601String();
        $checker->assertNoConflict($targetUserId, $targetSchedAt, $targetEndsAt, ['personal' => $calendarActivity->id]);

        $calendarActivity->update(array_filter([
            'user_id'      => $validated['user_id']      ?? $calendarActivity->user_id,
            'type'         => $validated['type']         ?? $calendarActivity->type,
            'title'        => $validated['title']        ?? $calendarActivity->title,
            'description'  => array_key_exists('description', $validated) ? $validated['description'] : $calendarActivity->description,
            'scheduled_at' => array_key_exists('scheduled_at', $validated) ? $validated['scheduled_at'] : $calendarActivity->scheduled_at,
            'ends_at'      => array_key_exists('ends_at', $validated) ? $validated['ends_at'] : $calendarActivity->ends_at,
        ], fn($v) => $v !== null || true));

        if ($request->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Activity updated.');
    }

    public function complete(CalendarActivity $calendarActivity)
    {
        $this->authorizeEdit($calendarActivity);
        $calendarActivity->update(['status' => 'completed', 'completed_at' => now()]);
        if (request()->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Activity completed.');
    }

    public function cancel(CalendarActivity $calendarActivity)
    {
        $this->authorizeEdit($calendarActivity);
        $calendarActivity->update(['status' => 'cancelled']);
        if (request()->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Activity cancelled.');
    }

    public function notCompleted(CalendarActivity $calendarActivity)
    {
        $this->authorizeEdit($calendarActivity);
        $calendarActivity->update(['status' => 'not_completed']);
        if (request()->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Activity marked as not completed.');
    }

    public function markPending(CalendarActivity $calendarActivity)
    {
        $this->authorizeEdit($calendarActivity);
        $calendarActivity->update(['status' => 'pending', 'completed_at' => null]);
        if (request()->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Activity moved back to pending.');
    }

    public function destroy(CalendarActivity $calendarActivity)
    {
        $this->authorizeEdit($calendarActivity);
        $calendarActivity->delete();
        if (request()->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Activity deleted.');
    }

    /**
     * GET /admin/{area}/calendar/availability
     *   ?user_id=X&scheduled_at=ISO[&ends_at=ISO][&exclude_lead=id][&exclude_personal=id]
     *
     * Devuelve las actividades pendientes del user que se solapan con el rango
     * [scheduled_at, ends_at] (donde end = start si no se manda ends_at).
     * Misma regla de overlap que assertNoConflict — los modales muestran
     * warning rojo + deshabilitan Save. Backend bloquea hard al persistir.
     */
    public function availability(Request $request, string $area, \App\Services\CalendarAvailabilityChecker $checker)
    {
        $validated = $request->validate([
            'user_id'      => 'required|exists:users,id',
            'scheduled_at' => 'required|date',
            'ends_at'      => 'nullable|date',
            'exclude_lead'     => 'nullable|integer',
            'exclude_personal' => 'nullable|integer',
        ]);

        if (!in_array($area, ['sponsorship', 'sales'], true)) abort(404);
        $this->authorizeArea($area);

        $excludes = [];
        if (!empty($validated['exclude_lead'])) {
            $excludes[$area === 'sponsorship' ? 'sponsorship_lead' : 'sales_lead'] = (int) $validated['exclude_lead'];
        }
        if (!empty($validated['exclude_personal'])) {
            $excludes['personal'] = (int) $validated['exclude_personal'];
        }

        $start = \Carbon\Carbon::parse($validated['scheduled_at']);
        $end = !empty($validated['ends_at']) ? \Carbon\Carbon::parse($validated['ends_at']) : $start->copy();

        $conflicts = $checker->getConflicts((int) $validated['user_id'], $start, $end, $excludes);

        // Mismo filtro que assertNoConflict: si nueva y existente son ambas
        // punto-en-el-tiempo, NO se considera conflicto.
        $newHasRange = !empty($validated['ends_at']);
        $conflicts = $conflicts->filter(fn($c) => !empty($c['ends_at']) || $newHasRange)->values();

        return response()->json(['conflicts' => $conflicts]);
    }

    // ─────────────────────────── Helpers ───────────────────────────

    private function validatePayload(Request $request, bool $creating): array
    {
        $rules = [
            'user_id'      => 'nullable|exists:users,id',
            'type'         => ['nullable', Rule::in(CalendarActivity::TYPES)],
            'title'        => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'ends_at'      => 'nullable|date|after:scheduled_at',
            'area'         => ['nullable', Rule::in(['sales', 'sponsorship'])],
        ];

        if ($creating) {
            $rules['type']  = ['required', Rule::in(CalendarActivity::TYPES)];
            $rules['title'] = 'required|string|max:255';
            // area opcional: las personales nuevas son globales para no atar la
            // disponibilidad del user a un área específica.
            $rules['area']  = ['nullable', Rule::in(['sales', 'sponsorship'])];
        }

        return $request->validate($rules);
    }

    private function authorizeArea(string $area): void
    {
        $u = auth()->user();
        if (!$u || !$u->canManageArea($area)) {
            abort(403, 'You do not have access to this area.');
        }
    }

    private function authorizeEdit(CalendarActivity $a): void
    {
        $u = auth()->user();
        if (!$u) abort(403);
        if ($u->role === 'admin') return;
        // Leader of the area (cuando la actividad tiene área asignada).
        if ($a->area && $u->isLeaderOf($a->area)) return;
        // Owner (assigned) or creator can edit their own — única regla cuando la
        // actividad es global (area=null): es privada al user dueño.
        if ($a->user_id === $u->id || $a->created_by_user_id === $u->id) return;
        abort(403, 'You do not have access to this activity.');
    }
}
