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
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, true);
        $user = auth()->user();

        $this->authorizeArea($validated['area']);

        $activity = CalendarActivity::create([
            'user_id'            => $validated['user_id'] ?? $user->id,
            'created_by_user_id' => $user->id,
            'area'               => $validated['area'],
            'type'               => $validated['type'],
            'title'              => $validated['title'],
            'description'        => $validated['description'] ?? null,
            'scheduled_at'       => $validated['scheduled_at'] ?? null,
            'status'             => 'pending',
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'activity' => $activity], 201);
        }
        return back()->with('success', 'Activity created.');
    }

    public function update(Request $request, CalendarActivity $calendarActivity)
    {
        $this->authorizeEdit($calendarActivity);

        $validated = $this->validatePayload($request, false);

        $calendarActivity->update(array_filter([
            'user_id'      => $validated['user_id']      ?? $calendarActivity->user_id,
            'type'         => $validated['type']         ?? $calendarActivity->type,
            'title'        => $validated['title']        ?? $calendarActivity->title,
            'description'  => array_key_exists('description', $validated) ? $validated['description'] : $calendarActivity->description,
            'scheduled_at' => array_key_exists('scheduled_at', $validated) ? $validated['scheduled_at'] : $calendarActivity->scheduled_at,
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

    // ─────────────────────────── Helpers ───────────────────────────

    private function validatePayload(Request $request, bool $creating): array
    {
        $rules = [
            'user_id'      => 'nullable|exists:users,id',
            'type'         => ['nullable', Rule::in(CalendarActivity::TYPES)],
            'title'        => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'area'         => ['nullable', Rule::in(['sales', 'sponsorship'])],
        ];

        if ($creating) {
            $rules['type']  = ['required', Rule::in(CalendarActivity::TYPES)];
            $rules['title'] = 'required|string|max:255';
            $rules['area']  = ['required', Rule::in(['sales', 'sponsorship'])];
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
        // Admin or leader of the area always.
        if ($a->area && $u->isLeaderOf($a->area)) return;
        // Owner (assigned) or creator can edit their own.
        if ($a->user_id === $u->id || $a->created_by_user_id === $u->id) return;
        abort(403, 'You do not have access to this activity.');
    }
}
