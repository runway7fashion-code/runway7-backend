<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignerLead;
use App\Models\Event;
use App\Models\LeadActivity;
use App\Models\LeadTag;
use App\Models\SalesBotMessage;
use App\Models\User;
use App\Services\LeadAssignmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';

        $query = DesignerLead::with(['events:id,name', 'assignedTo:id,first_name,last_name', 'tags:id,name,color'])
            ->whereNull('deleted_at');

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
            $query->where('status', $request->status);
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

        // Stats
        $baseQuery = DesignerLead::whereNull('deleted_at');
        if (!$isLeader) {
            $baseQuery->where('assigned_to', $user->id);
        }

        $stats = [
            'total'       => (clone $baseQuery)->count(),
            'new'         => (clone $baseQuery)->where('status', 'new')->count(),
            'contacted'   => (clone $baseQuery)->where('status', 'contacted')->count(),
            'follow_up'   => (clone $baseQuery)->where('status', 'follow_up')->count(),
            'interested'  => (clone $baseQuery)->where('status', 'interested')->count(),
            'negotiating' => (clone $baseQuery)->where('status', 'negotiating')->count(),
            'converted'   => (clone $baseQuery)->where('status', 'converted')->count(),
            'lost'        => (clone $baseQuery)->where('status', 'lost')->count(),
            'unassigned'  => (clone $baseQuery)->whereNull('assigned_to')->count(),
        ];

        $perPage = in_array($request->per_page, [20, 50, 100, 200]) ? $request->per_page : 20;

        $leads = $query->orderByRaw("CASE WHEN status = 'new' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Advisors list for assignment filter/dropdown
        $advisors = $isLeader
            ? User::where('role', 'sales')->whereNull('deleted_at')->select('id', 'first_name', 'last_name', 'sales_type', 'is_available')->get()
            : collect();

        $events = Event::whereNull('deleted_at')->select('id', 'name')->orderBy('start_date', 'desc')->get();

        return Inertia::render('Admin/Sales/Leads/Index', [
            'leads'    => $leads,
            'stats'    => $stats,
            'statuses' => DesignerLead::STATUSES,
            'advisors' => $advisors,
            'events'   => $events,
            'allTags'  => LeadTag::orderBy('name')->get(['id', 'name', 'color']),
            'filters'  => $request->only(['search', 'status', 'event', 'assigned_to', 'budget', 'tag', 'per_page']),
            'isLeader' => $isLeader,
        ]);
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
        ]);

        $advisors = $isLeader
            ? User::where('role', 'sales')->whereNull('deleted_at')->select('id', 'first_name', 'last_name', 'sales_type', 'is_available')->get()
            : collect();

        $events = Event::whereNull('deleted_at')->select('id', 'name')->orderBy('start_date', 'desc')->get();

        return Inertia::render('Admin/Sales/Leads/Show', [
            'lead'     => $lead,
            'statuses' => DesignerLead::STATUSES,
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
            ? User::where('role', 'sales')->whereNull('deleted_at')->select('id', 'first_name', 'last_name')->get()
            : collect();

        return Inertia::render('Admin/Sales/Leads/Create', [
            'events'   => $events,
            'advisors' => $advisors,
            'isLeader' => $isLeader,
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
            'event_id'               => 'required|exists:events,id',
            'preferred_contact_time' => 'nullable|string|max:20',
            'assigned_to'            => 'nullable|exists:users,id',
            'notes'                  => 'nullable|string',
        ]);

        $eventId = $validated['event_id'];
        unset($validated['event_id']);

        // Auto-assign to current user if not leader
        $user = auth()->user();
        $isLeader = $user->role === 'admin' || $user->sales_type === 'lider';
        if (!$isLeader) {
            $validated['assigned_to'] = $user->id;
        }

        // Check if lead with this email already exists
        $existingLead = DesignerLead::where('email', $validated['email'])->first();

        if ($existingLead) {
            // Check if this event is already linked
            if ($existingLead->events()->where('events.id', $eventId)->exists()) {
                return back()->withErrors(['email' => 'Este prospecto ya está registrado para este evento.'])->withInput();
            }

            // Add new event to existing lead
            $existingLead->events()->attach($eventId);

            LeadActivity::create([
                'lead_id'      => $existingLead->id,
                'user_id'      => auth()->id(),
                'type'         => 'system',
                'title'        => 'Nuevo evento agregado: ' . Event::find($eventId)?->name,
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            return redirect()->route('admin.sales.leads.show', $existingLead)
                ->with('success', 'Evento agregado al prospecto existente.');
        }

        $lead = DesignerLead::create(array_merge($validated, [
            'status' => 'new',
            'source' => 'manual',
        ]));

        // Link event
        $lead->events()->attach($eventId);

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'system',
            'title'        => 'Lead creado manualmente',
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

        return redirect()->route('admin.sales.leads.show', $lead)
            ->with('success', 'Prospecto creado correctamente.');
    }

    public function edit(DesignerLead $lead)
    {
        $events = Event::whereNull('deleted_at')->select('id', 'name')->orderBy('start_date', 'desc')->get();
        $advisors = User::where('role', 'sales')->whereNull('deleted_at')->select('id', 'first_name', 'last_name')->get();

        return Inertia::render('Admin/Sales/Leads/Edit', [
            'lead'     => $lead,
            'events'   => $events,
            'advisors' => $advisors,
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
            'event_id'               => 'nullable|exists:events,id',
            'preferred_contact_time' => 'nullable|string|max:20',
            'notes'                  => 'nullable|string',
        ]);

        $lead->update($validated);

        return redirect()->route('admin.sales.leads.show', $lead)
            ->with('success', 'Prospecto actualizado correctamente.');
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

        return back()->with('success', 'Estado actualizado.');
    }

    public function updateEventStatus(Request $request, DesignerLead $lead)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'status'   => 'required|string|in:' . implode(',', array_keys(DesignerLead::STATUSES)),
        ]);

        $leadEvent = $lead->leadEvents()->where('event_id', $request->event_id)->first();
        if (!$leadEvent) {
            return back()->with('error', 'Este lead no está registrado para este evento.');
        }

        $oldStatus = $leadEvent->status;
        $leadEvent->update(['status' => $request->status]);

        $eventName = Event::find($request->event_id)?->name ?? '';

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'status_change',
            'title'        => "Estado de {$eventName}: {$oldStatus} → {$request->status}",
            'description'  => DesignerLead::STATUSES[$oldStatus]['label'] . ' → ' . DesignerLead::STATUSES[$request->status]['label'],
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Update global lead status to the most advanced event status
        $allStatuses = $lead->leadEvents()->pluck('status')->toArray();
        $statusPriority = ['converted', 'negotiating', 'interested', 'follow_up', 'contacted', 'new', 'no_response', 'no_contact', 'lost', 'spam'];
        foreach ($statusPriority as $s) {
            if (in_array($s, $allStatuses)) {
                $lead->update(['status' => $s]);
                break;
            }
        }

        return back()->with('success', 'Estado del evento actualizado.');
    }

    public function assign(Request $request, DesignerLead $lead)
    {
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

        return back()->with('success', "Prospecto asignado a {$advisor->first_name}.");
    }

    public function syncTags(Request $request, DesignerLead $lead)
    {
        $request->validate(['tag_ids' => 'array', 'tag_ids.*' => 'exists:lead_tags,id']);
        $lead->tags()->sync($request->tag_ids ?? []);
        return back()->with('success', 'Tags actualizados.');
    }

    public function addActivity(Request $request, DesignerLead $lead)
    {
        $validated = $request->validate([
            'type'         => 'required|string|in:' . implode(',', array_keys(LeadActivity::TYPES)),
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $activity = LeadActivity::create(array_merge($validated, [
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'status'  => $validated['scheduled_at'] ? 'pending' : 'completed',
            'completed_at' => $validated['scheduled_at'] ? null : now(),
        ]));

        // Update last_contacted_at for call/email/meeting types
        if (in_array($validated['type'], ['call', 'email', 'meeting']) && !$validated['scheduled_at']) {
            $lead->update(['last_contacted_at' => now()]);
        }

        // Immediate bot notification if activity is scheduled within the next hour
        if ($validated['scheduled_at']) {
            $scheduledTime = \Carbon\Carbon::parse($validated['scheduled_at'], 'America/Lima');
            $nowLima = now('America/Lima');
            $minutesUntil = $nowLima->diffInMinutes($scheduledTime, false);

            if ($minutesUntil > 0 && $minutesUntil <= 60) {
                SalesBotMessage::create([
                    'user_id'      => auth()->id(),
                    'type'         => 'reminder',
                    'title'        => "Actividad en {$minutesUntil} min: {$activity->title}",
                    'message'      => "Tienes programado a las {$scheduledTime->format('g:i A')}: {$activity->title} — {$lead->full_name} ({$lead->company_name})",
                    'action_url'   => "/admin/sales/leads/{$lead->id}",
                    'action_label' => 'Ver prospecto',
                ]);
            }

            // If activity is already overdue, notify immediately
            if ($minutesUntil < 0) {
                SalesBotMessage::create([
                    'user_id'      => auth()->id(),
                    'type'         => 'overdue',
                    'title'        => "Actividad vencida: {$activity->title}",
                    'message'      => "Esta actividad ya pasó su hora programada ({$scheduledTime->format('g:i A')}): {$activity->title} — {$lead->full_name}",
                    'action_url'   => "/admin/sales/leads/{$lead->id}",
                    'action_label' => 'Ver prospecto',
                ]);
            }
        }

        return back()->with('success', 'Actividad registrada.');
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

        return back()->with('success', 'Actividad completada.');
    }

    public function toggleAvailability(Request $request)
    {
        $user = auth()->user();
        $user->update(['is_available' => !$user->is_available]);

        return back()->with('success', $user->is_available ? 'Estás disponible para recibir leads.' : 'No recibirás nuevos leads.');
    }

    public function destroy(DesignerLead $lead)
    {
        // Delete related activities (soft delete doesn't trigger cascade)
        $lead->activities()->delete();

        // Delete bot messages that reference this lead
        SalesBotMessage::where('action_url', 'like', "%/leads/{$lead->id}%")
            ->delete();

        $lead->forceDelete();

        return redirect()->route('admin.sales.leads.index')->with('success', 'Prospecto eliminado.');
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
            ? User::where('role', 'sales')->whereNull('deleted_at')->select('id', 'first_name', 'last_name')->get()
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

    public function botMessages()
    {
        $messages = SalesBotMessage::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
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

    public function botMarkAllRead()
    {
        SalesBotMessage::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }
}
