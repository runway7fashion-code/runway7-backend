<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Sponsorship\Category;
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

        // Asesores ven solo lo suyo; líderes ven todo el equipo.
        $scopedAdvisorId = $isLider ? null : $user->id;

        // ─── Leads totals + by status ───
        $leadsQuery = Lead::query();
        if ($scopedAdvisorId) $leadsQuery->where('assigned_to_user_id', $scopedAdvisorId);
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
        if ($scopedAdvisorId) $registrationsQuery->where('created_by_user_id', $scopedAdvisorId);
        if ($eventId)         $registrationsQuery->where('event_id', $eventId);

        $contractsInRange = (clone $registrationsQuery)->whereBetween('created_at', [$from, $to])->count();
        $contractsTotal   = (clone $registrationsQuery)->count();

        // ─── Top 3 categorías con más contratos cerrados (en el rango) ───
        $topCategoriesRaw = Registration::query()
            ->whereBetween('sponsorship_registrations.created_at', [$from, $to])
            ->when($scopedAdvisorId, fn($q) => $q->where('created_by_user_id', $scopedAdvisorId))
            ->when($eventId, fn($q) => $q->where('event_id', $eventId))
            ->join('sponsorship_leads', 'sponsorship_leads.id', '=', 'sponsorship_registrations.lead_id')
            ->whereNotNull('sponsorship_leads.category_id')
            ->selectRaw('sponsorship_leads.category_id, COUNT(*) as contracts_count, COALESCE(SUM(agreed_price),0) as total_revenue')
            ->groupBy('sponsorship_leads.category_id')
            ->orderByDesc('contracts_count')
            ->limit(3)
            ->get();

        $categoryNames = Category::whereIn('id', $topCategoriesRaw->pluck('category_id'))
            ->pluck('name', 'id')
            ->toArray();

        $topCategories = $topCategoriesRaw->map(fn($row) => [
            'category_id'     => $row->category_id,
            'name'            => $categoryNames[$row->category_id] ?? 'Unknown',
            'contracts_count' => (int) $row->contracts_count,
            'total_revenue'   => (float) $row->total_revenue,
        ])->values();

        return Inertia::render('Admin/Sponsorship/Dashboard', [
            'stats' => [
                'leadsTotal'       => $leadsTotal,
                'leadsByStatus'    => $leadsByStatus,
                'leadsInRange'     => $leadsInRange,
                'sponsorsTotal'    => $sponsorsTotal,
                'contractsTotal'   => $contractsTotal,
                'contractsInRange' => $contractsInRange,
            ],
            'topCategories' => $topCategories,
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
