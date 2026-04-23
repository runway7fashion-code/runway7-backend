<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\LeadsImport;
use App\Models\DesignerLead;
use App\Models\Event;
use App\Models\LeadActivity;
use App\Models\LeadTag;
use App\Models\SalesBotMessage;
use App\Models\User;
use App\Models\Country;
use App\Models\DesignerCategory;
use App\Services\LeadAssignmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';

        $query = DesignerLead::with(['events:id,name', 'assignedTo:id,first_name,last_name', 'tags:id,name,color']);

        // Advisors only see their leads
        if (!$isLeader) {
            $query->where('assigned_to', $user->id);
        }

        // Filters
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'ilike', "%{$s}%")
                  ->orWhere('last_name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
                  ->orWhere('company_name', 'ilike', "%{$s}%")
                  ->orWhere('phone', 'ilike', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('event')) {
            $query->whereHas('events', fn($q) => $q->where('events.id', $request->event));
        }

        if ($request->filled('assigned_to')) {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        if ($request->filled('budget')) {
            $query->where('budget', $request->budget);
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', fn($q) => $q->where('lead_tags.id', $request->tag));
        }

        if ($request->filled('opp_status')) {
            if ($request->opp_status === 'total') {
                $query->whereHas('leadEvents');
            } else {
                $query->whereHas('leadEvents', fn($q) => $q->where('status', $request->opp_status));
            }
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Stats
        $baseQuery = DesignerLead::query();
        if (!$isLeader) {
            $baseQuery->where('assigned_to', $user->id);
        }

        // Lead stats (marketing)
        $stats = [
            'total'     => (clone $baseQuery)->count(),
            'new'       => (clone $baseQuery)->where('status', 'new')->count(),
            'qualified' => (clone $baseQuery)->where('status', 'qualified')->count(),
            'client'    => (clone $baseQuery)->where('status', 'client')->count(),
            'lost'      => (clone $baseQuery)->where('status', 'lost')->count(),
            'spam'      => (clone $baseQuery)->where('status', 'spam')->count(),
            'unassigned' => (clone $baseQuery)->whereNull('assigned_to')->count(),
            'redirected' => (clone $baseQuery)->where('status', 'redirected')->count(),
        ];

        // Opportunity stats (ventas) — respect user role
        $oppBase = \DB::table('lead_events')
            ->join('designer_leads', 'designer_leads.id', '=', 'lead_events.lead_id');
        if (!$isLeader) {
            $oppBase->where('designer_leads.assigned_to', $user->id);
        }
        $opportunityStats = [
            'opp_total'      => (clone $oppBase)->count(),
            'opp_new'        => (clone $oppBase)->where('lead_events.status', 'new')->count(),
            'opp_contacted'  => (clone $oppBase)->where('lead_events.status', 'contacted')->count(),
            'opp_follow_up'  => (clone $oppBase)->where('lead_events.status', 'follow_up')->count(),
            'opp_negotiating'=> (clone $oppBase)->where('lead_events.status', 'negotiating')->count(),
            'opp_converted'  => (clone $oppBase)->where('lead_events.status', 'converted')->count(),
            'opp_lost'       => (clone $oppBase)->where('lead_events.status', 'lost')->count(),
        ];

        $perPage = in_array($request->per_page, [20, 50, 100, 200]) ? $request->per_page : 20;

        $leads = $query->orderByRaw("CASE WHEN status = 'new' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Advisors list for assignment filter/dropdown
        $advisors = $isLeader
            ? User::where('role', 'sales')->select('id', 'first_name', 'last_name', 'sales_type', 'is_available')->get()
            : collect();

        $events = Event::whereNull('deleted_at')->select('id', 'name')->orderBy('start_date', 'desc')->get();

        return Inertia::render('Admin/Sales/Leads/Index', [
            'leads'              => $leads,
            'stats'              => $stats,
            'opportunityStats'   => $opportunityStats,
            'statuses'           => DesignerLead::STATUSES,
            'opportunityStatuses'=> DesignerLead::OPPORTUNITY_STATUSES,
            'sources'            => DesignerLead::SOURCES,
            'advisors'           => $advisors,
            'events'             => $events,
            'allTags'            => LeadTag::orderBy('name')->get(['id', 'name', 'color']),
            'filters'            => $request->only(['search', 'status', 'opp_status', 'event', 'assigned_to', 'budget', 'tag', 'source', 'per_page']),
            'isLeader'           => $isLeader,
        ]);
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';

        $query = DesignerLead::with(['events:id,name', 'assignedTo:id,first_name,last_name', 'tags:id,name']);
        if (!$isLeader) $query->where('assigned_to', $user->id);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('first_name', 'ilike', "%{$s}%")->orWhere('last_name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%")->orWhere('company_name', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('source')) $query->where('source', $request->source);
        if ($request->filled('assigned_to')) $query->where('assigned_to', $request->assigned_to);

        $leads = $query->orderByDesc('created_at')->get();

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Name', 'Email', 'Phone', 'Company', 'Country', 'Source', 'Status', 'Assigned To', 'Events', 'Tags', 'Budget', 'Created At']);
        foreach ($leads as $lead) {
            fputcsv($csv, [
                $lead->first_name . ' ' . $lead->last_name,
                $lead->email,
                $lead->phone,
                $lead->company_name,
                $lead->country,
                DesignerLead::SOURCES[$lead->source] ?? $lead->source,
                DesignerLead::STATUSES[$lead->status]['label'] ?? $lead->status,
                $lead->assignedTo ? $lead->assignedTo->first_name . ' ' . $lead->assignedTo->last_name : 'Unassigned',
                $lead->events->pluck('name')->join(', '),
                $lead->tags->pluck('name')->join(', '),
                $lead->budget,
                $lead->created_at->format('Y-m-d H:i'),
            ]);
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="leads-' . now()->format('Y-m-d') . '.csv"');
    }

    public function show(DesignerLead $lead)
    {
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';

        if (!$isLeader && $lead->assigned_to !== $user->id) {
            abort(403);
        }

        $lead->load([
            'events:id,name,city,start_date',
            'tags:id,name,color',
            'assignedTo:id,first_name,last_name',
            'convertedDesigner:id,first_name,last_name',
            'activities.user:id,first_name,last_name',
            'activities.files',
        ]);

        $advisors = $isLeader
            ? User::where('role', 'sales')->select('id', 'first_name', 'last_name', 'sales_type', 'is_available')->get()
            : collect();

        $events = Event::whereNull('deleted_at')->select('id', 'name')->orderBy('start_date', 'desc')->get();

        return Inertia::render('Admin/Sales/Leads/Show', [
            'lead'     => $lead,
            'statuses' => DesignerLead::STATUSES,
            'opportunityStatuses' => DesignerLead::OPPORTUNITY_STATUSES,
            'sources' => DesignerLead::SOURCES,
            'activityTypes' => LeadActivity::TYPES,
            'advisors' => $advisors,
            'events'   => $events,
            'allTags'  => LeadTag::orderBy('name')->get(['id', 'name', 'color']),
            'isLeader' => $isLeader,
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';
        $events = Event::whereNull('deleted_at')->select('id', 'name')->orderBy('start_date', 'desc')->get();
        $advisors = $isLeader
            ? User::where('role', 'sales')->select('id', 'first_name', 'last_name')->get()
            : collect();

        return Inertia::render('Admin/Sales/Leads/Create', [
            'events'     => $events,
            'advisors'   => $advisors,
            'sources'    => DesignerLead::SOURCES,
            'isLeader'   => $isLeader,
            'categories' => DesignerCategory::ordered()->get(['id', 'name']),
            'phoneCodes' => Country::active()->ordered()->get(['name', 'code', 'phone', 'flag']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'             => 'required|string|max:100',
            'last_name'              => 'required|string|max:100',
            'email'                  => 'required|email|max:255',
            'phone'                  => 'nullable|string|max:30',
            'country'                => 'nullable|string|max:100',
            'company_name'           => 'nullable|string|max:255',
            'retail_category'        => 'nullable|string|max:100',
            'website_url'            => 'nullable|url|max:500',
            'instagram'              => 'nullable|string|max:100',
            'designs_ready'          => 'nullable|string|max:50',
            'budget'                 => 'nullable|string|max:100',
            'past_shows'             => 'nullable|string|max:10',
            'event_ids'              => 'required|array|min:1',
            'event_ids.*'            => 'exists:events,id',
            'preferred_contact_time' => 'nullable|string|max:20',
            'assigned_to'            => 'nullable|exists:users,id',
            'source'                 => 'nullable|string|max:50',
            'notes'                  => 'nullable|string',
            'note_title'             => 'nullable|string|max:255',
            'note_files'             => 'nullable|array',
            'note_files.*'           => 'file|max:10240',
        ]);

        $eventIds = $validated['event_ids'];
        unset($validated['event_ids']);

        // Auto-assign to current user if not leader
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';
        if (!$isLeader) {
            $validated['assigned_to'] = $user->id;
        }

        // Check if lead with this email already exists
        $existingLead = DesignerLead::where('email', $validated['email'])->first();

        if ($existingLead) {
            $existingEventIds = $existingLead->events()->pluck('events.id')->toArray();
            $newEventIds = array_diff($eventIds, $existingEventIds);

            if (empty($newEventIds)) {
                return back()->withErrors(['event_ids' => 'This prospect is already registered for all selected events.'])->withInput();
            }

            foreach ($newEventIds as $eid) {
                $existingLead->events()->attach($eid);
            }

            $eventNames = Event::whereIn('id', $newEventIds)->pluck('name')->join(', ');
            LeadActivity::create([
                'lead_id'      => $existingLead->id,
                'user_id'      => auth()->id(),
                'type'         => 'system',
                'title'        => 'Evento(s) agregado(s): ' . $eventNames,
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            return redirect()->route('admin.sales.leads.show', $existingLead)
                ->with('success', 'Event(s) added to existing prospect.');
        }

        $lead = DesignerLead::create(array_merge($validated, [
            'status' => 'new',
            'source' => $validated['source'] ?? 'manual',
        ]));

        // Link events
        foreach ($eventIds as $eid) {
            $lead->events()->attach($eid);
        }

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'system',
            'title'        => 'Lead created manually',
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        if ($lead->assigned_to) {
            LeadActivity::create([
                'lead_id'      => $lead->id,
                'user_id'      => auth()->id(),
                'type'         => 'assignment',
                'title'        => 'Asignado a ' . User::find($lead->assigned_to)?->first_name,
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        }

        // Create initial note if provided
        if (!empty($validated['notes'])) {
            $note = LeadActivity::create([
                'lead_id'      => $lead->id,
                'user_id'      => auth()->id(),
                'type'         => 'note',
                'title'        => $validated['note_title'] ?? 'Nota',
                'description'  => $validated['notes'],
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            if ($request->hasFile('note_files')) {
                foreach ($request->file('note_files') as $file) {
                    $path = $file->store('lead-files', 'public');
                    $note->files()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                }
            }
        }

        return redirect()->route('admin.sales.leads.show', $lead)
            ->with('success', 'Prospect created successfully.');
    }

    public function edit(DesignerLead $lead)
    {
        $lead->load('events');
        $events = Event::whereNull('deleted_at')->select('id', 'name')->orderBy('start_date', 'desc')->get();
        $advisors = User::where('role', 'sales')->select('id', 'first_name', 'last_name')->get();

        return Inertia::render('Admin/Sales/Leads/Edit', [
            'lead'     => $lead,
            'events'   => $events,
            'opportunityStatuses' => DesignerLead::OPPORTUNITY_STATUSES,
            'advisors' => $advisors,
            'sources'  => DesignerLead::SOURCES,
            'categories' => DesignerCategory::ordered()->get(['id', 'name']),
            'phoneCodes' => Country::active()->ordered()->get(['name', 'code', 'phone', 'flag']),
        ]);
    }

    public function update(Request $request, DesignerLead $lead)
    {
        $validated = $request->validate([
            'first_name'             => 'required|string|max:100',
            'last_name'              => 'required|string|max:100',
            'email'                  => 'required|email|max:255',
            'phone'                  => 'nullable|string|max:30',
            'country'                => 'nullable|string|max:100',
            'company_name'           => 'nullable|string|max:255',
            'retail_category'        => 'nullable|string|max:100',
            'website_url'            => 'nullable|url|max:500',
            'instagram'              => 'nullable|string|max:100',
            'designs_ready'          => 'nullable|string|max:50',
            'budget'                 => 'nullable|string|max:100',
            'past_shows'             => 'nullable|string|max:10',
            'preferred_contact_time' => 'nullable|string|max:20',
            'source'                 => 'nullable|string|max:50',
            'notes'                  => 'nullable|string',
            'event_ids'              => 'nullable|array',
            'event_ids.*'            => 'exists:events,id',
            'event_statuses'         => 'nullable|array',
        ]);

        $eventIds = $validated['event_ids'] ?? [];
        $eventStatuses = $validated['event_statuses'] ?? [];
        unset($validated['event_ids'], $validated['event_statuses']);

        $lead->update($validated);

        // Sync events: add new, remove unchecked (protect converted)
        $currentEventIds = $lead->leadEvents()->pluck('event_id')->toArray();
        $newIds = array_diff($eventIds, $currentEventIds);
        $removeIds = array_diff($currentEventIds, $eventIds);

        // Add new events
        foreach ($newIds as $eid) {
            $lead->events()->attach($eid, ['status' => $eventStatuses[$eid] ?? 'new']);
        }

        // Remove events (protect converted)
        foreach ($removeIds as $eid) {
            $leadEvent = $lead->leadEvents()->where('event_id', $eid)->first();
            if ($leadEvent && $leadEvent->status !== 'converted') {
                $lead->events()->detach($eid);
            }
        }

        // Update statuses of existing events
        foreach ($eventStatuses as $eid => $status) {
            if (in_array($eid, $eventIds)) {
                $lead->leadEvents()->where('event_id', $eid)->update(['status' => $status]);
            }
        }

        $lead->recalculateStatus();

        return redirect()->route('admin.sales.leads.show', $lead)
            ->with('success', 'Prospect updated successfully.');
    }

    public function updateStatus(Request $request, DesignerLead $lead)
    {
        $request->validate(['status' => 'required|string|in:' . implode(',', array_keys(DesignerLead::STATUSES))]);

        $oldStatus = $lead->status;
        $lead->update(['status' => $request->status]);

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'status_change',
            'title'        => "Estado cambiado de {$oldStatus} a {$request->status}",
            'description'  => DesignerLead::STATUSES[$oldStatus]['label'] . ' → ' . DesignerLead::STATUSES[$request->status]['label'],
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Auto-assign via round-robin when lead is qualified and not yet assigned
        if ($request->status === 'qualified' && !$lead->assigned_to) {
            $assignmentService = new LeadAssignmentService();
            $assignedTo = $assignmentService->assignRoundRobin($lead);

            if ($assignedTo) {
                $lead->refresh();
                $assignmentService->scheduleInitialCall($lead);
            }
        }

        return back()->with('success', 'Status updated.');
    }

    public function updateEventStatus(Request $request, DesignerLead $lead)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'status'   => 'required|string|in:' . implode(',', array_keys(DesignerLead::OPPORTUNITY_STATUSES)),
        ]);

        $leadEvent = $lead->leadEvents()->where('event_id', $request->event_id)->first();
        if (!$leadEvent) {
            return back()->with('error', 'This lead is not registered for this event.');
        }

        $oldStatus = $leadEvent->status;
        $leadEvent->update(['status' => $request->status]);

        $eventName = Event::find($request->event_id)?->name ?? '';

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'status_change',
            'title'        => "Estado de {$eventName}: {$oldStatus} → {$request->status}",
            'description'  => (DesignerLead::OPPORTUNITY_STATUSES[$oldStatus]['label'] ?? $oldStatus) . ' → ' . (DesignerLead::OPPORTUNITY_STATUSES[$request->status]['label'] ?? $request->status),
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Auto-recalculate lead status based on all event statuses
        $lead->recalculateStatus();

        return back()->with('success', 'Event status updated.');
    }

    public function assign(Request $request, DesignerLead $lead)
    {
        $user = auth()->user();
        if ($user->role !== 'admin' && $user->sales_type !== 'lider') {
            abort(403, 'Only leaders can reassign advisors.');
        }

        $request->validate(['assigned_to' => 'required|exists:users,id']);

        $advisor = User::find($request->assigned_to);
        $lead->update(['assigned_to' => $advisor->id]);

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'assignment',
            'title'        => "Asignado manualmente a {$advisor->first_name} {$advisor->last_name}",
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('success', "Prospect assigned to {$advisor->first_name}.");
    }

    public function redirectToOperations(Request $request, DesignerLead $lead)
    {
        $request->validate([
            'redirect_type' => 'required|in:model,media,volunteer',
            'redirect_note' => 'nullable|string|max:500',
        ]);

        $lead->update([
            'status'          => 'redirected',
            'redirect_type'   => $request->redirect_type,
            'redirect_status' => 'new',
            'redirect_note'   => $request->redirect_note,
            'redirected_by'   => auth()->id(),
            'redirected_at'   => now(),
        ]);

        $typeLabels = ['model' => 'Model', 'media' => 'Media', 'volunteer' => 'Volunteer'];

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'system',
            'title'        => 'Sent to Operations as ' . ($typeLabels[$request->redirect_type] ?? $request->redirect_type),
            'description'  => $request->redirect_note,
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Lead sent to Operations.');
    }

    public function addEvent(Request $request, DesignerLead $lead)
    {
        $request->validate(['event_id' => 'required|exists:events,id']);

        if ($lead->events()->where('events.id', $request->event_id)->exists()) {
            return back()->with('error', 'This prospect is already registered for this event.');
        }

        $lead->events()->attach($request->event_id);

        $eventName = Event::find($request->event_id)?->name;
        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'system',
            'title'        => 'Evento agregado: ' . $eventName,
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('success', "Event {$eventName} added.");
    }

    public function removeEvent(Request $request, DesignerLead $lead)
    {
        $request->validate(['event_id' => 'required|exists:events,id']);

        $leadEvent = $lead->leadEvents()->where('event_id', $request->event_id)->first();
        if (!$leadEvent) {
            return back()->with('error', 'This event is not assigned.');
        }

        // Don't allow removing converted events
        if ($leadEvent->status === 'converted') {
            return back()->with('error', 'Cannot remove an event with a closed sale.');
        }

        $eventName = Event::find($request->event_id)?->name;
        $lead->events()->detach($request->event_id);

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'system',
            'title'        => 'Evento removido: ' . $eventName,
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Recalculate lead status
        $lead->recalculateStatus();

        return back()->with('success', "Event {$eventName} removed.");
    }

    public function syncTags(Request $request, DesignerLead $lead)
    {
        $request->validate(['tag_ids' => 'array', 'tag_ids.*' => 'exists:lead_tags,id']);
        $lead->tags()->sync($request->tag_ids ?? []);
        return back()->with('success', 'Tags updated.');
    }

    public function addActivity(Request $request, DesignerLead $lead)
    {
        $validated = $request->validate([
            'type'         => 'required|string|in:' . implode(',', array_keys(LeadActivity::TYPES)),
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'files'        => 'nullable|array',
            'files.*'      => 'file|max:10240',
        ]);

        $activity = LeadActivity::create(array_merge($validated, [
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'status'  => ($validated['scheduled_at'] ?? null) ? 'pending' : 'completed',
            'completed_at' => ($validated['scheduled_at'] ?? null) ? null : now(),
        ]));

        // Save attached files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('lead-files', 'public');
                $activity->files()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        // Update last_contacted_at for call/email/meeting types
        if (in_array($validated['type'], ['call', 'email', 'meeting']) && !($validated['scheduled_at'] ?? null)) {
            $lead->update(['last_contacted_at' => now()]);
        }

        // Immediate bot notification if activity is scheduled within the next hour
        if ($validated['scheduled_at'] ?? null) {
            $scheduledTime = \Carbon\Carbon::parse($validated['scheduled_at'], 'America/Lima');
            $nowLima = now('America/Lima');
            $minutesUntil = $nowLima->diffInMinutes($scheduledTime, false);

            if ($minutesUntil > 0 && $minutesUntil <= 60) {
                SalesBotMessage::create([
                    'user_id'      => auth()->id(),
                    'type'         => 'reminder',
                    'title'        => "Activity in {$minutesUntil} min: {$activity->title}",
                    'message'      => "Tienes programado a las {$scheduledTime->format('g:i A')}: {$activity->title} — {$lead->full_name} ({$lead->company_name})",
                    'action_url'   => "/admin/sales/leads/{$lead->id}",
                    'action_label' => 'View prospect',
                ]);
            }

            // If activity is already overdue, notify immediately
            if ($minutesUntil < 0) {
                SalesBotMessage::create([
                    'user_id'      => auth()->id(),
                    'type'         => 'overdue',
                    'title'        => "Overdue activity: {$activity->title}",
                    'message'      => "Esta actividad ya pasó su hora programada ({$scheduledTime->format('g:i A')}): {$activity->title} — {$lead->full_name}",
                    'action_url'   => "/admin/sales/leads/{$lead->id}",
                    'action_label' => 'View prospect',
                ]);
            }
        }

        return back()->with('success', 'Activity created.');
    }

    public function completeActivity(LeadActivity $activity)
    {
        $activity->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        if (in_array($activity->type, ['call', 'email', 'meeting'])) {
            $activity->lead->update(['last_contacted_at' => now()]);
        }

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Activity completed.');
    }

    public function cancelActivity(LeadActivity $activity)
    {
        $activity->update(['status' => 'cancelled']);

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Activity cancelled.');
    }

    public function notCompletedActivity(LeadActivity $activity)
    {
        $activity->update(['status' => 'not_completed']);

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Activity marked as not completed.');
    }

    public function toggleAvailability(Request $request)
    {
        $user = auth()->user();
        $user->update(['is_available' => !$user->is_available]);

        return back()->with('success', $user->is_available ? 'You are now available to receive leads.' : 'You will no longer receive new leads.');
    }

    /**
     * Return leads for auto-complete in designer create form
     */
    public function search(Request $request)
    {
        $query = DesignerLead::query();

        $fields = ['id', 'first_name', 'last_name', 'email', 'phone', 'country', 'company_name', 'retail_category', 'website_url', 'instagram', 'budget', 'assigned_to'];

        // Search by ID directly
        if ($request->filled('id')) {
            $lead = $query->where('id', $request->id)
                ->select($fields)
                ->with('events:id,name')
                ->first();
            return response()->json($lead ? [$lead] : []);
        }

        // Search by text
        $leads = $query->where('status', '!=', 'spam')
            ->where(function ($q) use ($request) {
                $s = $request->q;
                $q->where('first_name', 'ilike', "%{$s}%")
                  ->orWhere('last_name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
                  ->orWhere('company_name', 'ilike', "%{$s}%");
            })
            ->select($fields)
            ->with('events:id,name')
            ->limit(10)
            ->get();

        return response()->json($leads);
    }

    public function calendar()
    {
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';

        $advisors = $isLeader
            ? User::where('role', 'sales')->select('id', 'first_name', 'last_name')->get()
            : collect();

        return Inertia::render('Admin/Sales/Calendar', [
            'advisors' => $advisors,
            'isLeader' => $isLeader,
            'activityTypes' => LeadActivity::TYPES,
        ]);
    }

    public function calendarEvents(Request $request)
    {
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';

        $query = LeadActivity::whereNotNull('scheduled_at')
            ->with(['lead:id,first_name,last_name,company_name', 'user:id,first_name,last_name']);

        if (!$isLeader) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('advisor')) {
            $query->where('user_id', $request->advisor);
        }

        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('scheduled_at', [$request->start, $request->end]);
        }

        $events = $query->get()->map(fn($a) => [
            'id'          => $a->id,
            'title'       => $a->title,
            'start'       => $a->scheduled_at->toIso8601String(),
            'type'        => $a->type,
            'status'      => $a->status,
            'lead_id'     => $a->lead_id,
            'lead_name'   => $a->lead?->full_name,
            'company'     => $a->lead?->company_name,
            'advisor'     => $a->user ? $a->user->first_name . ' ' . $a->user->last_name : null,
            'description' => $a->description,
        ]);

        return response()->json($events);
    }

    public function botAsk(Request $request)
    {
        $request->validate(['message' => 'required|string|max:500']);

        // Save user message
        SalesBotMessage::create([
            'user_id'      => auth()->id(),
            'type'         => 'user_msg',
            'title'        => '',
            'message'      => $request->message,
            'is_read'      => true,
        ]);

        $botService = new \App\Services\R7BotService();
        $response = $botService->ask(auth()->user(), $request->message);

        // Save bot response
        SalesBotMessage::create([
            'user_id'      => auth()->id(),
            'type'         => 'bot_response',
            'title'        => 'R7',
            'message'      => $response,
            'is_read'      => true,
        ]);

        return response()->json(['response' => $response]);
    }

    public function botMessages()
    {
        $messages = SalesBotMessage::where('user_id', auth()->id())
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        $unreadCount = SalesBotMessage::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'messages'     => $messages,
            'unread_count' => $unreadCount,
        ]);
    }

    public function botMarkRead(Request $request)
    {
        $request->validate(['id' => 'required|exists:sales_bot_messages,id']);

        SalesBotMessage::where('id', $request->id)
            ->where('user_id', auth()->id())
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    public function downloadImportTemplate()
    {
        return Excel::download(new \App\Exports\LeadsTemplateExport(), 'leads_import_template.xlsx');
    }

    public function importLeads(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'event_id'    => 'nullable|exists:events,id',
            'assigned_to' => 'nullable|exists:users,id',
            'source'      => 'nullable|string|max:50',
        ]);

        $eventId    = $request->filled('event_id') ? (int) $request->event_id : null;
        $assignedTo = $request->filled('assigned_to') ? (int) $request->assigned_to : null;
        $source     = $request->filled('source') ? $request->source : null;

        $import = new LeadsImport(
            globalEventId: $eventId,
            assignedTo: $assignedTo,
            source: $source,
        );
        Excel::import($import, $request->file('file'));

        $s = $import->summary;
        $msg = "Import completed: {$s['created']} created, {$s['updated']} updated, {$s['assigned']} assigned to events.";

        if (!empty($s['errors'])) {
            $msg .= ' ' . count($s['errors']) . ' errors (see log).';
            \Illuminate\Support\Facades\Log::warning('LeadsImport errors', $s['errors']);
        }

        return back()->with('success', $msg)->with('importSummary', $s);
    }

    public function botMarkAllRead()
    {
        SalesBotMessage::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }
}
