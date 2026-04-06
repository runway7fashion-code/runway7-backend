<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignerLead;
use App\Models\Event;
use App\Models\LeadActivity;
use App\Models\LeadEvent;
use App\Models\LeadTag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeadAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';

        // Date range defaults: last 30 days
        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();
        $eventId = $request->input('event');
        $advisorId = $request->input('advisor');

        // Base query scoped by role
        $baseQuery = DesignerLead::whereNull('deleted_at');
        if (!$isLeader) {
            $baseQuery->where('assigned_to', $user->id);
        }

        // ──────────────────────────────────────
        // 1. KPI Cards
        // ──────────────────────────────────────
        $periodLeads = (clone $baseQuery)->whereBetween('created_at', [$from, $to]);
        if ($advisorId) $periodLeads->where('assigned_to', $advisorId);

        $totalLeadsPeriod = (clone $periodLeads)->count();
        $newLeads = (clone $periodLeads)->where('status', 'new')->count();
        $qualifiedLeads = (clone $periodLeads)->where('status', 'qualified')->count();
        $clientLeads = (clone $periodLeads)->where('status', 'client')->count();
        $lostLeads = (clone $periodLeads)->where('status', 'lost')->count();
        $spamLeads = (clone $periodLeads)->where('status', 'spam')->count();

        // All-time totals (for overall conversion rate)
        $allTimeQuery = clone $baseQuery;
        if ($advisorId) $allTimeQuery->where('assigned_to', $advisorId);
        $totalLeadsAll = (clone $allTimeQuery)->count();
        $totalClients = (clone $allTimeQuery)->where('status', 'client')->count();
        $conversionRate = $totalLeadsAll > 0 ? round(($totalClients / $totalLeadsAll) * 100, 1) : 0;

        // Opportunity stats (event-level)
        $oppQuery = LeadEvent::query();
        if (!$isLeader) {
            $oppQuery->whereHas('lead', fn($q) => $q->where('assigned_to', $user->id)->whereNull('deleted_at'));
        } else {
            $oppQuery->whereHas('lead', fn($q) => $q->whereNull('deleted_at'));
        }
        if ($eventId) $oppQuery->where('event_id', $eventId);
        if ($advisorId) {
            $oppQuery->whereHas('lead', fn($q) => $q->where('assigned_to', $advisorId));
        }

        $oppConverted = (clone $oppQuery)->where('status', 'converted')->count();
        $oppLost = (clone $oppQuery)->where('status', 'lost')->count();
        $oppTotal = (clone $oppQuery)->count();

        // Average conversion time (days from lead created_at to event status → converted)
        $avgConversionDays = LeadEvent::where('lead_events.status', 'converted')
            ->whereHas('lead', function ($q) use ($isLeader, $user, $advisorId) {
                $q->whereNull('designer_leads.deleted_at');
                if (!$isLeader) $q->where('designer_leads.assigned_to', $user->id);
                if ($advisorId) $q->where('designer_leads.assigned_to', $advisorId);
            })
            ->when($eventId, fn($q) => $q->where('lead_events.event_id', $eventId))
            ->join('designer_leads', 'lead_events.lead_id', '=', 'designer_leads.id')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (lead_events.updated_at - designer_leads.created_at)) / 86400) as avg_days')
            ->value('avg_days');
        $avgConversionDays = $avgConversionDays ? round($avgConversionDays, 1) : null;

        $kpis = [
            'total_period' => $totalLeadsPeriod,
            'new' => $newLeads,
            'qualified' => $qualifiedLeads,
            'clients' => $clientLeads,
            'lost' => $lostLeads,
            'spam' => $spamLeads,
            'conversion_rate' => $conversionRate,
            'opp_converted' => $oppConverted,
            'opp_lost' => $oppLost,
            'opp_total' => $oppTotal,
            'avg_conversion_days' => $avgConversionDays,
        ];

        // ──────────────────────────────────────
        // 2. Lead Funnel (all time, filtered)
        // ──────────────────────────────────────
        $funnelQuery = clone $baseQuery;
        if ($advisorId) $funnelQuery->where('assigned_to', $advisorId);
        $funnel = [];
        foreach (['new', 'qualified', 'client', 'lost', 'spam'] as $status) {
            $funnel[] = [
                'status' => $status,
                'label' => DesignerLead::STATUSES[$status]['label'],
                'color' => DesignerLead::STATUSES[$status]['color'],
                'count' => (clone $funnelQuery)->where('status', $status)->count(),
            ];
        }

        // ──────────────────────────────────────
        // 3. Opportunity Pipeline
        // ──────────────────────────────────────
        $pipelineQuery = LeadEvent::query();
        if (!$isLeader) {
            $pipelineQuery->whereHas('lead', fn($q) => $q->where('assigned_to', $user->id)->whereNull('deleted_at'));
        } else {
            $pipelineQuery->whereHas('lead', fn($q) => $q->whereNull('deleted_at'));
        }
        if ($eventId) $pipelineQuery->where('event_id', $eventId);
        if ($advisorId) {
            $pipelineQuery->whereHas('lead', fn($q) => $q->where('assigned_to', $advisorId));
        }

        $pipeline = [];
        foreach (['new', 'contacted', 'follow_up', 'negotiating', 'converted', 'lost'] as $status) {
            $pipeline[] = [
                'status' => $status,
                'label' => DesignerLead::OPPORTUNITY_STATUSES[$status]['label'],
                'color' => DesignerLead::OPPORTUNITY_STATUSES[$status]['color'],
                'count' => (clone $pipelineQuery)->where('status', $status)->count(),
            ];
        }

        // ──────────────────────────────────────
        // 4. Leads by Source
        // ──────────────────────────────────────
        $sourceQuery = (clone $baseQuery)->whereBetween('created_at', [$from, $to]);
        if ($advisorId) $sourceQuery->where('assigned_to', $advisorId);

        $sourceData = (clone $sourceQuery)
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($baseQuery, $advisorId) {
                $sourceLeadIds = (clone $baseQuery)
                    ->where('source', $row->source)
                    ->when($advisorId, fn($q) => $q->where('assigned_to', $advisorId))
                    ->pluck('id');
                $converted = LeadEvent::whereIn('lead_id', $sourceLeadIds)->where('status', 'converted')->distinct('lead_id')->count('lead_id');
                return [
                    'source' => $row->source,
                    'label' => DesignerLead::SOURCES[$row->source] ?? $row->source,
                    'total' => $row->total,
                    'converted' => $converted,
                    'rate' => $row->total > 0 ? round(($converted / $row->total) * 100, 1) : 0,
                ];
            });

        // ──────────────────────────────────────
        // 5. Advisor Performance (leaders only)
        // ──────────────────────────────────────
        $advisorPerformance = [];
        if ($isLeader) {
            $advisors = User::where('role', 'sales')->get(['id', 'first_name', 'last_name']);
            foreach ($advisors as $adv) {
                $advLeads = DesignerLead::whereNull('deleted_at')->where('assigned_to', $adv->id);
                $advLeadsPeriod = (clone $advLeads)->whereBetween('created_at', [$from, $to])->count();
                $advTotal = (clone $advLeads)->count();
                $advClients = (clone $advLeads)->where('status', 'client')->count();
                $advActivities = LeadActivity::where('user_id', $adv->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->whereNotIn('type', ['status_change', 'assignment', 'system'])
                    ->count();
                $advCompleted = LeadActivity::where('user_id', $adv->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->where('status', 'completed')
                    ->whereNotIn('type', ['status_change', 'assignment', 'system'])
                    ->count();
                $advConvRate = $advTotal > 0 ? round(($advClients / $advTotal) * 100, 1) : 0;

                $advisorPerformance[] = [
                    'id' => $adv->id,
                    'name' => $adv->first_name . ' ' . $adv->last_name,
                    'leads_period' => $advLeadsPeriod,
                    'leads_total' => $advTotal,
                    'clients' => $advClients,
                    'activities' => $advActivities,
                    'completed_activities' => $advCompleted,
                    'conversion_rate' => $advConvRate,
                ];
            }
        }

        // ──────────────────────────────────────
        // 6. Activity over time (last 30 days or period)
        // ──────────────────────────────────────
        $activityQuery = LeadActivity::whereBetween('created_at', [$from, $to]);
        if (!$isLeader) {
            $activityQuery->where('user_id', $user->id);
        }
        if ($advisorId) $activityQuery->where('user_id', $advisorId);

        $activityByDay = (clone $activityQuery)
            ->whereNotIn('type', ['status_change', 'assignment', 'system'])
            ->select(
                DB::raw("DATE(created_at) as date"),
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $activityByType = (clone $activityQuery)
            ->whereNotIn('type', ['status_change', 'assignment', 'system'])
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->get()
            ->map(fn($row) => [
                'type' => $row->type,
                'label' => LeadActivity::TYPES[$row->type]['label'] ?? $row->type,
                'color' => LeadActivity::TYPES[$row->type]['color'] ?? '#6B7280',
                'total' => $row->total,
            ]);

        // ──────────────────────────────────────
        // 7. Leads by Country (top 15)
        // ──────────────────────────────────────
        $countryQuery = (clone $baseQuery)->whereNotNull('country')->where('country', '!=', '');
        if ($advisorId) $countryQuery->where('assigned_to', $advisorId);

        $countryData = (clone $countryQuery)
            ->select('country', DB::raw('COUNT(*) as total'))
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(function ($row) use ($baseQuery, $advisorId) {
                $countryLeadIds = (clone $baseQuery)
                    ->where('country', $row->country)
                    ->when($advisorId, fn($q) => $q->where('assigned_to', $advisorId))
                    ->pluck('id');
                $converted = LeadEvent::whereIn('lead_id', $countryLeadIds)->where('status', 'converted')->distinct('lead_id')->count('lead_id');
                return [
                    'country' => $row->country,
                    'total' => $row->total,
                    'converted' => $converted,
                    'rate' => $row->total > 0 ? round(($converted / $row->total) * 100, 1) : 0,
                ];
            });

        // ──────────────────────────────────────
        // 8. Tags performance
        // ──────────────────────────────────────
        $tagsData = LeadTag::withCount('leads')->get()->map(function ($tag) {
            $tagLeadIds = $tag->leads()->whereNull('designer_leads.deleted_at')->pluck('designer_leads.id');
            $converted = $tagLeadIds->isNotEmpty()
                ? LeadEvent::whereIn('lead_id', $tagLeadIds)->where('status', 'converted')->distinct('lead_id')->count('lead_id')
                : 0;
            $total = $tagLeadIds->count();
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'leads_count' => $total,
                'converted' => $converted,
                'rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
            ];
        })->sortByDesc('leads_count')->values();

        // ──────────────────────────────────────
        // 9. Leads over time (for line chart)
        // ──────────────────────────────────────
        $leadsOverTime = (clone $baseQuery)
            ->whereBetween('created_at', [$from, $to])
            ->when($advisorId, fn($q) => $q->where('assigned_to', $advisorId))
            ->select(DB::raw("DATE(created_at) as date"), DB::raw("COUNT(*) as total"))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Filters data
        $events = Event::orderByDesc('start_date')->get(['id', 'name']);
        $advisors = $isLeader ? User::where('role', 'sales')->get(['id', 'first_name', 'last_name']) : collect();

        return Inertia::render('Admin/Sales/Analytics', [
            'kpis' => $kpis,
            'funnel' => $funnel,
            'pipeline' => $pipeline,
            'sourceData' => $sourceData,
            'advisorPerformance' => $advisorPerformance,
            'activityByDay' => $activityByDay,
            'activityByType' => $activityByType,
            'countryData' => $countryData,
            'tagsData' => $tagsData,
            'leadsOverTime' => $leadsOverTime,
            'events' => $events,
            'advisors' => $advisors,
            'filters' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'event' => $eventId,
                'advisor' => $advisorId,
            ],
            'isLeader' => $isLeader,
        ]);
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';

        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();
        $advisorId = $request->input('advisor');

        $query = DesignerLead::with(['events:id,name', 'assignedTo:id,first_name,last_name', 'tags:id,name', 'leadEvents'])
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$from, $to]);

        if (!$isLeader) {
            $query->where('assigned_to', $user->id);
        }
        if ($advisorId) $query->where('assigned_to', $advisorId);

        $leads = $query->orderByDesc('created_at')->get();

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Name', 'Email', 'Phone', 'Company', 'Country', 'Source', 'Status', 'Assigned To', 'Events', 'Event Statuses', 'Tags', 'Budget', 'Created At']);

        foreach ($leads as $lead) {
            $eventNames = $lead->events->pluck('name')->join(', ');
            $eventStatuses = $lead->leadEvents->map(fn($le) => (DesignerLead::OPPORTUNITY_STATUSES[$le->status]['label'] ?? $le->status))->join(', ');
            $tags = $lead->tags->pluck('name')->join(', ');
            fputcsv($csv, [
                $lead->first_name . ' ' . $lead->last_name,
                $lead->email,
                $lead->phone,
                $lead->company_name,
                $lead->country,
                DesignerLead::SOURCES[$lead->source] ?? $lead->source,
                DesignerLead::STATUSES[$lead->status]['label'] ?? $lead->status,
                $lead->assignedTo ? $lead->assignedTo->first_name . ' ' . $lead->assignedTo->last_name : 'Unassigned',
                $eventNames,
                $eventStatuses,
                $tags,
                $lead->budget,
                $lead->created_at->format('Y-m-d H:i'),
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="leads-report-' . now()->format('Y-m-d') . '.csv"');
    }
}
