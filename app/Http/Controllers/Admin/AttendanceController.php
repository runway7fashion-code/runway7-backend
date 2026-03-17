<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceController extends Controller
{
    /** Roles visibles para el rol 'operation' */
    private const OPERATION_ROLES = ['designer', 'model', 'media', 'volunteer'];

    public function index(Request $request): Response
    {
        $query = Checkin::with([
            'user',
            'event:id,name',
            'eventDay:id,date,label',
            'creator:id,first_name,last_name',
        ]);

        // Operation solo ve designers, models, media y voluntarios
        if (auth()->user()->role === 'operation') {
            $query->whereHas('user', fn ($q) => $q->whereIn('role', self::OPERATION_ROLES));
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->filled('event_day_id')) {
            $query->where('event_day_id', $request->event_day_id);
        }

        if ($request->filled('role')) {
            $query->whereHas('user', fn ($q) => $q->where('role', $request->role));
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn ($q) => $q
                ->where('first_name', 'ilike', "%{$search}%")
                ->orWhere('last_name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
            );
        }

        $checkins = $query->orderBy('checked_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        // Área por usuario (voluntarios/staff desde event_staff)
        $checkinCollection = $checkins->getCollection();
        $this->attachAreaToCheckins($checkinCollection);
        $checkins->setCollection($checkinCollection);

        $events = Event::where('status', '!=', 'cancelled')
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name']);

        $eventDays = $request->filled('event_id')
            ? EventDay::where('event_id', $request->event_id)->orderBy('date')->get(['id', 'date', 'label'])
            : collect();

        // Resumen del día actual (respetando restricción de operation)
        $isOperation = auth()->user()->role === 'operation';
        $summaryBase = fn () => Checkin::whereDate('checked_at', today())
            ->when($isOperation, fn ($q) => $q->whereHas('user', fn ($u) => $u->whereIn('role', self::OPERATION_ROLES)));

        $todayCount  = $summaryBase()->count();
        $entryCount  = $summaryBase()->where('type', 'entry')->count();
        $exitCount   = $summaryBase()->where('type', 'exit')->count();
        $singleCount = $summaryBase()->where('type', 'single')->count();

        return Inertia::render('Admin/Attendance/Index', [
            'checkins'        => $checkins,
            'filters'         => $request->only(['event_id', 'event_day_id', 'role', 'method', 'type', 'search']),
            'events'          => $events,
            'event_days'      => $eventDays,
            'summary'         => compact('todayCount', 'entryCount', 'exitCount', 'singleCount'),
            'allowed_roles'   => $isOperation ? self::OPERATION_ROLES : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'      => 'required|exists:users,id',
            'event_id'     => 'required|exists:events,id',
            'event_day_id' => 'required|exists:event_days,id',
            'type'         => 'required|in:entry,exit,single',
            'checked_at'   => 'required|date',
            'notes'        => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($request->user_id);

        // Validar duplicados
        $needsEntryExit = Checkin::needsEntryExit($user);

        if ($needsEntryExit) {
            $exists = Checkin::where('user_id', $user->id)
                ->where('event_day_id', $request->event_day_id)
                ->where('type', $request->type)
                ->exists();

            if ($exists) {
                return back()->with('error', "Este usuario ya tiene marcación de {$request->type} para este día.");
            }
        } else {
            $exists = Checkin::where('user_id', $user->id)
                ->where('event_day_id', $request->event_day_id)
                ->where('type', 'single')
                ->exists();

            if ($exists) {
                return back()->with('error', 'Este usuario ya tiene marcación para este día.');
            }

            // Forzar tipo single para roles que no tienen entrada/salida
            $request->merge(['type' => 'single']);
        }

        Checkin::create([
            'user_id'      => $user->id,
            'event_id'     => $request->event_id,
            'event_day_id' => $request->event_day_id,
            'type'         => $request->type,
            'checked_at'   => $request->checked_at,
            'method'       => 'manual',
            'notes'        => $request->notes,
            'created_by'   => auth()->id(),
        ]);

        return back()->with('success', 'Marcación registrada correctamente.');
    }

    public function destroy(Checkin $checkin)
    {
        $checkin->delete();
        return back()->with('success', 'Marcación eliminada.');
    }

    public function export(Request $request)
    {
        $filename = 'asistencia_' . now()->format('Ymd_His') . '.xlsx';

        $isOperation = auth()->user()->role === 'operation';

        return Excel::download(new AttendanceExport(
            eventId:          $request->input('event_id'),
            eventDayId:       $request->input('event_day_id'),
            role:             $request->input('role'),
            method:           $request->input('method'),
            search:           $request->input('search'),
            restrictToRoles:  $isOperation ? self::OPERATION_ROLES : null,
        ), $filename);
    }

    public function eventDays(Event $event)
    {
        return response()->json(
            EventDay::where('event_id', $event->id)
                ->orderBy('date')
                ->get(['id', 'date', 'label'])
        );
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function attachAreaToCheckins($checkins): void
    {
        // Agrupar por user_id+event_id para hacer una sola query
        $pairs = $checkins
            ->filter(fn ($c) => in_array($c->user?->role, ['volunteer', 'staff']))
            ->map(fn ($c) => ['user_id' => $c->user_id, 'event_id' => $c->event_id])
            ->unique(fn ($p) => $p['user_id'] . '_' . $p['event_id']);

        if ($pairs->isEmpty()) return;

        $areaMap = [];
        foreach ($pairs as $pair) {
            $area = \DB::table('event_staff')
                ->where('user_id', $pair['user_id'])
                ->where('event_id', $pair['event_id'])
                ->value('area');
            $areaMap[$pair['user_id'] . '_' . $pair['event_id']] = $area;
        }

        foreach ($checkins as $checkin) {
            $key = $checkin->user_id . '_' . $checkin->event_id;
            $checkin->area = $areaMap[$key] ?? null;
        }
    }
}
