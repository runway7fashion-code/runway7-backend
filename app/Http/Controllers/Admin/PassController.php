<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventPass;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PassController extends Controller
{
    public function index(Request $request): Response
    {
        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name', 'start_date', 'status']);

        $selectedEventId = $request->input('event_id');

        $query = EventPass::with(['user:id,first_name,last_name,email', 'issuedBy:id,first_name,last_name', 'event:id,name']);

        if ($selectedEventId) {
            $query->forEvent($selectedEventId);
        }

        if ($request->filled('type')) {
            $query->byType($request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('holder_name', 'ilike', "%{$q}%")
                    ->orWhere('holder_email', 'ilike', "%{$q}%")
                    ->orWhere('qr_code', 'ilike', "%{$q}%");
            });
        }

        $passes = $query->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (EventPass $p) => [
                'id'           => $p->id,
                'qr_code'      => $p->qr_code,
                'pass_type'    => $p->pass_type,
                'pass_type_label' => $p->passTypeLabel(),
                'holder_name'  => $p->holder_name,
                'holder_email' => $p->holder_email,
                'status'       => $p->status,
                'valid_days'   => $p->valid_days,
                'checked_in_at' => $p->checked_in_at?->toISOString(),
                'notes'        => $p->notes,
                'issued_at'    => $p->issued_at->toISOString(),
                'event_name'   => $p->event?->name,
                'user'         => $p->user ? ['id' => $p->user->id, 'full_name' => $p->user->full_name] : null,
                'issued_by'    => $p->issuedBy ? ['id' => $p->issuedBy->id, 'full_name' => $p->issuedBy->full_name] : null,
            ]);

        $statsQuery = EventPass::query();
        if ($selectedEventId) {
            $statsQuery->forEvent($selectedEventId);
        }
        $allPasses = $statsQuery->get();
        $stats = [
            'total'     => $allPasses->count(),
            'active'    => $allPasses->where('status', 'active')->count(),
            'used'      => $allPasses->where('status', 'used')->count(),
            'cancelled' => $allPasses->where('status', 'cancelled')->count(),
            'by_type'   => $allPasses->groupBy('pass_type')->map->count(),
        ];

        $eventDays = $selectedEventId
            ? EventDay::where('event_id', $selectedEventId)
                ->orderBy('date')
                ->get(['id', 'date', 'label'])
                ->map(fn ($d) => [
                    'id'    => $d->id,
                    'label' => $d->label ?: $d->date->format('d M Y'),
                    'date'  => $d->date->format('Y-m-d'),
                ])
            : [];

        return Inertia::render('Admin/Passes/Index', [
            'events'          => $events,
            'selectedEventId' => $selectedEventId ? (int) $selectedEventId : null,
            'passes'          => $passes,
            'stats'           => $stats,
            'passTypes'       => EventPass::passTypes(),
            'eventDays'       => $eventDays,
            'filters'         => $request->only(['event_id', 'type', 'status', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'event_id'     => 'required|exists:events,id',
            'pass_type'    => 'required|string|in:' . implode(',', array_keys(EventPass::passTypes())),
            'holder_name'  => 'required|string|max:255',
            'holder_email' => 'nullable|email|max:255',
            'user_id'      => 'nullable|exists:users,id',
            'valid_days'   => 'nullable|array',
            'valid_days.*' => 'integer|exists:event_days,id',
            'notes'        => 'nullable|string|max:1000',
        ]);

        EventPass::create([
            'event_id'     => $request->event_id,
            'user_id'      => $request->user_id,
            'issued_by'    => $request->user()->id,
            'qr_code'      => EventPass::generateQrCode(),
            'pass_type'    => $request->pass_type,
            'holder_name'  => $request->holder_name,
            'holder_email' => $request->holder_email,
            'valid_days'   => $request->valid_days,
            'notes'        => $request->notes,
            'status'       => 'active',
        ]);

        return back()->with('success', 'Pase creado correctamente.');
    }

    public function update(Request $request, EventPass $pass)
    {
        $request->validate([
            'holder_name'  => 'sometimes|string|max:255',
            'holder_email' => 'nullable|email|max:255',
            'pass_type'    => 'sometimes|string|in:' . implode(',', array_keys(EventPass::passTypes())),
            'valid_days'   => 'nullable|array',
            'valid_days.*' => 'integer|exists:event_days,id',
            'notes'        => 'nullable|string|max:1000',
            'status'       => 'sometimes|string|in:active,cancelled',
        ]);

        $pass->update($request->only(['holder_name', 'holder_email', 'pass_type', 'valid_days', 'notes', 'status']));

        return back()->with('success', 'Pase actualizado.');
    }

    public function destroy(EventPass $pass)
    {
        $pass->update(['status' => 'cancelled']);

        return back()->with('success', 'Pase cancelado.');
    }

    public function reactivate(EventPass $pass)
    {
        if ($pass->status !== 'cancelled') {
            return back()->with('error', 'Solo se pueden reactivar pases cancelados.');
        }

        $pass->update(['status' => 'active']);

        return back()->with('success', 'Pase reactivado.');
    }

    public function checkIn(Request $request, EventPass $pass)
    {
        if ($pass->status !== 'active') {
            return back()->with('error', 'Este pase no está activo.');
        }

        $history   = $pass->check_in_history ?? [];
        $history[] = [
            'checked_in_at' => now()->toISOString(),
            'checked_by'    => $request->user()->id,
            'day_id'        => $request->input('day_id'),
        ];

        $pass->update([
            'status'           => 'used',
            'checked_in_at'    => now(),
            'check_in_history' => $history,
        ]);

        return back()->with('success', "Check-in registrado para {$pass->holder_name}.");
    }

    // API: buscar usuarios del sistema para autocompletar
    public function searchUsers(Request $request)
    {
        $q = $request->input('q', '');

        $users = User::where(function ($query) use ($q) {
                $query->where('first_name', 'ilike', "%{$q}%")
                    ->orWhere('last_name', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%");
            })
            ->whereIn('role', [...User::ROLES_PARTICIPANT, ...User::ROLES_ATTENDEE])
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'email', 'role']);

        return response()->json($users->map(fn ($u) => [
            'id'        => $u->id,
            'full_name' => $u->full_name,
            'email'     => $u->email,
            'role'      => $u->role,
        ]));
    }
}
