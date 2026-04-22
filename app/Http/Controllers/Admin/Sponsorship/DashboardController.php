<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Sponsorship\Lead;
use App\Models\Sponsorship\Registration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private function isLider(): bool
    {
        $u = auth()->user();
        return $u && ($u->role === 'admin' || ($u->role === 'sponsorship' && $u->sponsorship_type === 'lider'));
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $isLider = $this->isLider();

        // Rango: por defecto mes actual
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : Carbon::now()->startOfMonth();
        $to   = $request->filled('to')   ? Carbon::parse($request->to)->endOfDay()     : Carbon::now()->endOfMonth();
        $eventId = $request->filled('event_id') ? (int) $request->event_id : null;

        // ─── Leads totals + by status ───
        $leadsQuery = Lead::query();
        if (!$isLider) $leadsQuery->where('assigned_to_user_id', $user->id);
        if ($eventId) $leadsQuery->whereHas('events', fn($q) => $q->where('events.id', $eventId));

        $leadsTotal = (clone $leadsQuery)->count();
        $leadsByStatus = (clone $leadsQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $leadsInRange = (clone $leadsQuery)->whereBetween('created_at', [$from, $to])->count();

        // ─── Sponsors totals ───
        $sponsorsTotal = User::where('role', 'sponsor')->count();

        // ─── Contracts closed in range ───
        $registrationsQuery = Registration::query();
        if (!$isLider) $registrationsQuery->where('created_by_user_id', $user->id);
        if ($eventId)  $registrationsQuery->where('event_id', $eventId);

        $contractsInRange = (clone $registrationsQuery)->whereBetween('created_at', [$from, $to])->count();
        $contractsTotal   = (clone $registrationsQuery)->count();

        // ─── Ranking asesores (del mes/rango) ───
        $rankingQuery = Registration::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('created_by_user_id');
        if ($eventId) $rankingQuery->where('event_id', $eventId);

        $rankingRaw = $rankingQuery
            ->selectRaw('created_by_user_id, COUNT(*) as contracts_count, COALESCE(SUM(agreed_price),0) as total_revenue')
            ->groupBy('created_by_user_id')
            ->orderByDesc('contracts_count')
            ->limit(20)
            ->get();

        $advisorIds = $rankingRaw->pluck('created_by_user_id')->toArray();
        $advisors   = User::whereIn('id', $advisorIds)->get(['id', 'first_name', 'last_name', 'sponsorship_type'])->keyBy('id');

        $ranking = $rankingRaw->map(function ($row) use ($advisors) {
            $u = $advisors->get($row->created_by_user_id);
            return [
                'user_id'           => $row->created_by_user_id,
                'name'              => $u ? "{$u->first_name} {$u->last_name}" : 'Unknown',
                'sponsorship_type'  => $u->sponsorship_type ?? null,
                'contracts_count'   => (int) $row->contracts_count,
                'total_revenue'     => (float) $row->total_revenue,
            ];
        })->values();

        return Inertia::render('Admin/Sponsorship/Dashboard', [
            'stats' => [
                'leadsTotal'       => $leadsTotal,
                'leadsByStatus'    => $leadsByStatus,
                'leadsInRange'     => $leadsInRange,
                'sponsorsTotal'    => $sponsorsTotal,
                'contractsTotal'   => $contractsTotal,
                'contractsInRange' => $contractsInRange,
            ],
            'ranking' => $ranking,
            'filters' => [
                'from'     => $from->toDateString(),
                'to'       => $to->toDateString(),
                'event_id' => $eventId,
            ],
            'events'   => Event::whereNull('deleted_at')->orderBy('start_date', 'desc')->get(['id', 'name']),
            'statuses' => Lead::STATUSES,
            'isLider'  => $isLider,
        ]);
    }
}
