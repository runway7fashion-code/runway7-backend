<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Sponsorship\Category;
use App\Models\Sponsorship\Company;
use App\Models\Sponsorship\Lead;
use App\Models\Sponsorship\LeadActivity;
use App\Models\Sponsorship\LeadActivityFile;
use App\Models\Sponsorship\LeadEmail;
use App\Models\Sponsorship\Package;
use App\Models\Sponsorship\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadController extends Controller
{
    // ─────────────────────────── Helpers ───────────────────────────

    private function isLider(): bool
    {
        $u = auth()->user();
        if (!$u) return false;
        if ($u->role === 'admin') return true;
        return $u->role === 'sponsorship' && $u->sponsorship_type === 'lider';
    }

    private function authorizeSee(Lead $lead): void
    {
        if ($this->isLider()) return;
        if ($lead->assigned_to_user_id !== auth()->id()) {
            abort(403, 'This lead is not assigned to you.');
        }
    }

    private function validateEmailsUnique(array $emails, ?int $leadId = null): void
    {
        $normalized = array_map(fn($e) => mb_strtolower(trim($e)), $emails);
        $duplicates = array_unique(array_diff_assoc($normalized, array_unique($normalized)));
        if ($duplicates) {
            throw ValidationException::withMessages([
                'emails' => 'The same email cannot be repeated in the list.',
            ]);
        }
        foreach ($normalized as $email) {
            $query = LeadEmail::whereRaw('LOWER(email) = ?', [$email]);
            if ($leadId) $query->where('lead_id', '!=', $leadId);
            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'emails' => "The email {$email} is already registered in another lead.",
                ]);
            }
        }
    }

    private function logActivity(Lead $lead, string $type, string $title, ?string $description = null): void
    {
        LeadActivity::create([
            'lead_id'             => $lead->id,
            'created_by_user_id'  => auth()->id(),
            'assigned_to_user_id' => auth()->id(),
            'type'                => $type,
            'title'               => $title,
            'description'         => $description,
            'status'              => 'completed',
            'completed_at'        => now(),
        ]);
    }

    private function syncEmails(Lead $lead, string $primary, array $secondaries = []): void
    {
        $all = array_merge([$primary], $secondaries);
        $normalized = array_unique(array_map(fn($e) => mb_strtolower(trim($e)), $all));

        $lead->emails()->delete();
        foreach ($normalized as $email) {
            LeadEmail::create([
                'lead_id'    => $lead->id,
                'email'      => $email,
                'is_primary' => $email === mb_strtolower(trim($primary)),
            ]);
        }
    }

    // ─────────────────────────── Index + filtros ───────────────────────────

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Lead::query()->with([
            'company:id,name',
            'category:id,name',
            'assignedTo:id,first_name,last_name',
            'registeredBy:id,first_name,last_name',
            'primaryEmail',
        ]);

        // Visibilidad
        if (!$this->isLider()) {
            $query->where('assigned_to_user_id', $user->id);
        }

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to_user_id', $request->assigned_to);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('event_id')) {
            $eventId = $request->event_id;
            $query->whereHas('events', fn($q) => $q->where('events.id', $eventId));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('email_send')) {
            if ($request->email_send === 'none')    $query->whereNull('last_email_sent_at');
            if ($request->email_send === 'sent')    $query->where('last_email_status', 'sent');
            if ($request->email_send === 'failed')  $query->where('last_email_status', 'failed');
        }
        if ($request->filled('date_filter')) {
            $now = Carbon::now();
            switch ($request->date_filter) {
                case 'today':
                    $query->whereDate('created_at', $now->toDateString());
                    break;
                case 'week':
                    $query->where('created_at', '>=', $now->copy()->startOfWeek());
                    break;
                case 'month':
                    $query->where('created_at', '>=', $now->copy()->startOfMonth());
                    break;
                case 'year':
                    $query->where('created_at', '>=', $now->copy()->startOfYear());
                    break;
                case 'custom':
                    if ($request->filled('date_from')) {
                        $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
                    }
                    if ($request->filled('date_to')) {
                        $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
                    }
                    break;
            }
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'ilike', "%{$s}%")
                  ->orWhere('last_name', 'ilike', "%{$s}%")
                  ->orWhere('phone', 'ilike', "%{$s}%")
                  ->orWhereHas('emails', fn($e) => $e->where('email', 'ilike', "%{$s}%"))
                  ->orWhereHas('company', fn($c) => $c->where('name', 'ilike', "%{$s}%"));
            });
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // Counts por status (respetando visibilidad)
        $countsBase = Lead::query();
        if (!$this->isLider()) {
            $countsBase->where('assigned_to_user_id', $user->id);
        }
        $counts = $countsBase
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Opciones para filtros
        $advisors = $this->isLider()
            ? User::where('role', 'sponsorship')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'sponsorship_type'])
            : [];

        return Inertia::render('Admin/Sponsorship/Leads/Index', [
            'leads'     => $leads,
            'counts'    => $counts,
            'statuses'  => Lead::STATUSES,
            'sources'   => Lead::SOURCES,
            'filters'   => $request->only(['search', 'status', 'assigned_to', 'category_id', 'event_id', 'source', 'email_send', 'date_filter', 'date_from', 'date_to']),
            'advisors'  => $advisors,
            'categories'=> Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'events'    => Event::whereNull('deleted_at')->orderBy('start_date', 'desc')->get(['id', 'name']),
            'isLider'   => $this->isLider(),
        ]);
    }

    // ─────────────────────────── Create / Store ───────────────────────────

    public function create()
    {
        return Inertia::render('Admin/Sponsorship/Leads/Create', [
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'events'     => Event::whereNull('deleted_at')->orderBy('start_date', 'desc')->get(['id', 'name']),
            'tags'       => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'sources'    => Lead::SOURCES,
            'countries'  => \App\Models\Country::where('is_active', true)->orderBy('order')->orderBy('name')->get(['name', 'code', 'phone', 'flag']),
            'advisors'   => $this->isLider()
                ? User::where('role', 'sponsorship')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'sponsorship_type'])
                : [],
            'isLider'    => $this->isLider(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id'          => 'required|exists:sponsorship_companies,id',
            'first_name'          => 'required|string|max:100',
            'last_name'           => 'required|string|max:100',
            'email'               => 'required|email|max:255',
            'secondary_emails'    => 'nullable|array',
            'secondary_emails.*'  => 'email|max:255',
            'phone'               => 'nullable|string|max:30',
            'charge'              => 'nullable|string|max:150',
            'linkedin_url'        => 'nullable|url|max:500',
            'website_url'         => 'nullable|url|max:500',
            'instagram'           => 'nullable|string|max:150',
            'category_id'         => 'nullable|exists:sponsorship_categories,id',
            'source'              => ['required', Rule::in(Lead::SOURCES)],
            'source_detail'       => 'nullable|string|max:255',
            'event_ids'           => 'required|array|min:1',
            'event_ids.*'         => 'exists:events,id',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'notes'               => 'nullable|string',
            'tag_ids'             => 'nullable|array',
            'tag_ids.*'           => 'exists:sponsorship_tags,id',
        ]);

        // Validar email unique global (principal + secundarios)
        $this->validateEmailsUnique(array_merge([$validated['email']], $validated['secondary_emails'] ?? []));

        // Visibilidad de assigned_to: solo líder puede elegir. Asesor → auto a sí mismo.
        $assignedTo = $this->isLider()
            ? ($validated['assigned_to_user_id'] ?? auth()->id())
            : auth()->id();

        $lead = DB::transaction(function () use ($validated, $assignedTo) {
            $lead = Lead::create([
                'company_id'            => $validated['company_id'],
                'first_name'            => $validated['first_name'],
                'last_name'             => $validated['last_name'],
                'phone'                 => $validated['phone'] ?? null,
                'charge'                => $validated['charge'] ?? null,
                'linkedin_url'          => $validated['linkedin_url'] ?? null,
                'website_url'           => $validated['website_url'] ?? null,
                'instagram'             => $validated['instagram'] ?? null,
                'category_id'           => $validated['category_id'] ?? null,
                'status'                => 'nuevo',
                'source'                => $validated['source'],
                'source_detail'         => $validated['source_detail'] ?? null,
                'registered_by_user_id' => auth()->id(),
                'assigned_to_user_id'   => $assignedTo,
                'notes'                 => $validated['notes'] ?? null,
            ]);

            $this->syncEmails($lead, $validated['email'], $validated['secondary_emails'] ?? []);
            $lead->events()->sync($validated['event_ids']);
            if (!empty($validated['tag_ids'])) {
                $lead->tags()->sync($validated['tag_ids']);
            }

            $this->logActivity(
                $lead,
                'system',
                'Lead created manually',
                "Email: {$validated['email']}, Company: " . ($lead->company?->name ?? '—')
            );

            return $lead;
        });

        return redirect()->route('admin.sponsorship.leads.show', $lead)
            ->with('success', 'Lead created.');
    }

    // ─────────────────────────── Show ───────────────────────────

    public function show(Lead $lead)
    {
        $this->authorizeSee($lead);

        $lead->load([
            'company',
            'category:id,name',
            'assignedTo:id,first_name,last_name',
            'registeredBy:id,first_name,last_name',
            'convertedUser:id,first_name,last_name,email',
            'emails',
            'events:id,name,start_date,end_date',
            'tags:id,name,color',
            'activities.creator:id,first_name,last_name',
            'activities.assignedTo:id,first_name,last_name',
            'activities.editor:id,first_name,last_name',
            'activities.files',
        ]);

        return Inertia::render('Admin/Sponsorship/Leads/Show', [
            'lead'          => $lead,
            'statuses'      => Lead::STATUSES,
            'activityTypes' => LeadActivity::TYPES,
            'tags'          => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'events'        => Event::whereNull('deleted_at')->orderBy('start_date', 'desc')->get(['id', 'name']),
            'advisors'      => $this->isLider()
                ? User::where('role', 'sponsorship')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'sponsorship_type'])
                : User::where('role', 'sponsorship')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'sponsorship_type']),
            'isLider'       => $this->isLider(),
        ]);
    }

    // ─────────────────────────── Edit / Update ───────────────────────────

    public function edit(Lead $lead)
    {
        $this->authorizeSee($lead);

        $lead->load(['company:id,name', 'emails', 'events:id', 'tags:id']);

        return Inertia::render('Admin/Sponsorship/Leads/Edit', [
            'lead'       => $lead,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'events'     => Event::whereNull('deleted_at')->orderBy('start_date', 'desc')->get(['id', 'name']),
            'tags'       => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'sources'    => Lead::SOURCES,
            'countries'  => \App\Models\Country::where('is_active', true)->orderBy('order')->orderBy('name')->get(['name', 'code', 'phone', 'flag']),
            'advisors'   => $this->isLider()
                ? User::where('role', 'sponsorship')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'sponsorship_type'])
                : [],
            'isLider'    => $this->isLider(),
        ]);
    }

    public function update(Request $request, Lead $lead)
    {
        $this->authorizeSee($lead);

        $validated = $request->validate([
            'company_id'          => 'required|exists:sponsorship_companies,id',
            'first_name'          => 'required|string|max:100',
            'last_name'           => 'required|string|max:100',
            'email'               => 'required|email|max:255',
            'secondary_emails'    => 'nullable|array',
            'secondary_emails.*'  => 'email|max:255',
            'phone'               => 'nullable|string|max:30',
            'charge'              => 'nullable|string|max:150',
            'linkedin_url'        => 'nullable|url|max:500',
            'website_url'         => 'nullable|url|max:500',
            'instagram'           => 'nullable|string|max:150',
            'category_id'         => 'nullable|exists:sponsorship_categories,id',
            'source'              => ['required', Rule::in(Lead::SOURCES)],
            'source_detail'       => 'nullable|string|max:255',
            'event_ids'           => 'required|array|min:1',
            'event_ids.*'         => 'exists:events,id',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'notes'               => 'nullable|string',
            'tag_ids'             => 'nullable|array',
            'tag_ids.*'           => 'exists:sponsorship_tags,id',
        ]);

        $this->validateEmailsUnique(
            array_merge([$validated['email']], $validated['secondary_emails'] ?? []),
            $lead->id
        );

        // Solo líder puede cambiar assigned_to
        $assignedTo = $this->isLider()
            ? ($validated['assigned_to_user_id'] ?? $lead->assigned_to_user_id)
            : $lead->assigned_to_user_id;

        DB::transaction(function () use ($lead, $validated, $assignedTo) {
            $lead->update([
                'company_id'          => $validated['company_id'],
                'first_name'          => $validated['first_name'],
                'last_name'           => $validated['last_name'],
                'phone'               => $validated['phone'] ?? null,
                'charge'              => $validated['charge'] ?? null,
                'linkedin_url'        => $validated['linkedin_url'] ?? null,
                'website_url'         => $validated['website_url'] ?? null,
                'instagram'           => $validated['instagram'] ?? null,
                'category_id'         => $validated['category_id'] ?? null,
                'source'              => $validated['source'],
                'source_detail'       => $validated['source_detail'] ?? null,
                'assigned_to_user_id' => $assignedTo,
                'notes'               => $validated['notes'] ?? null,
            ]);

            $this->syncEmails($lead, $validated['email'], $validated['secondary_emails'] ?? []);
            $lead->events()->sync($validated['event_ids']);
            $lead->tags()->sync($validated['tag_ids'] ?? []);
        });

        return redirect()->route('admin.sponsorship.leads.show', $lead)
            ->with('success', 'Lead updated.');
    }

    // ─────────────────────────── Acciones rápidas ───────────────────────────

    public function updateStatus(Request $request, Lead $lead)
    {
        $this->authorizeSee($lead);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Lead::STATUSES))],
        ]);

        $oldStatus = $lead->status;
        if ($oldStatus === $validated['status']) {
            return back();
        }

        $lead->update(['status' => $validated['status']]);

        $oldLabel = Lead::STATUSES[$oldStatus]['label'] ?? $oldStatus;
        $newLabel = Lead::STATUSES[$validated['status']]['label'] ?? $validated['status'];
        $this->logActivity(
            $lead,
            'status_change',
            "Status changed from {$oldLabel} to {$newLabel}",
            "{$oldLabel} → {$newLabel}"
        );

        return back()->with('success', 'Status updated.');
    }

    public function assign(Request $request, Lead $lead)
    {
        if (!$this->isLider()) abort(403, 'Only a leader can reassign leads.');

        $validated = $request->validate([
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        $oldAssignedId = $lead->assigned_to_user_id;
        $newAssignedId = $validated['assigned_to_user_id'] ?? null;
        if ($oldAssignedId === $newAssignedId) {
            return back();
        }

        $lead->update(['assigned_to_user_id' => $newAssignedId]);

        $newUser = $newAssignedId ? User::find($newAssignedId) : null;
        $title = $newUser
            ? "Manually assigned to {$newUser->first_name} {$newUser->last_name}"
            : 'Lead unassigned';
        $this->logActivity($lead, 'assignment', $title);

        return back()->with('success', 'Assignment updated.');
    }

    public function syncTags(Request $request, Lead $lead)
    {
        $this->authorizeSee($lead);

        $validated = $request->validate([
            'tag_ids'   => 'nullable|array',
            'tag_ids.*' => 'exists:sponsorship_tags,id',
        ]);

        $lead->tags()->sync($validated['tag_ids'] ?? []);

        return back()->with('success', 'Tags updated.');
    }

    public function addEvent(Request $request, Lead $lead)
    {
        $this->authorizeSee($lead);

        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
        ]);

        $alreadyAttached = $lead->events()->where('events.id', $validated['event_id'])->exists();
        $lead->events()->syncWithoutDetaching([$validated['event_id']]);

        if (!$alreadyAttached) {
            $eventName = \App\Models\Event::find($validated['event_id'])?->name ?? '—';
            $this->logActivity($lead, 'system', "Event added: {$eventName}");
        }

        return back()->with('success', 'Event added.');
    }

    public function removeEvent(Request $request, Lead $lead)
    {
        $this->authorizeSee($lead);

        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
        ]);

        $eventName = \App\Models\Event::find($validated['event_id'])?->name ?? '—';
        $lead->events()->detach($validated['event_id']);

        $this->logActivity($lead, 'system', "Event removed: {$eventName}");

        return back()->with('success', 'Event removed.');
    }

    // ─────────────────────────── Direct email to lead ───────────────────────────

    public function sendEmail(Request $request, Lead $lead)
    {
        $this->authorizeSee($lead);

        $validated = $request->validate([
            'subject'       => 'required|string|max:255',
            'body'          => 'required|string',
            'is_contract'   => 'nullable|boolean',
            'attachments'   => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);

        $primary = $lead->emails()->where('is_primary', true)->first();
        if (!$primary) {
            return back()->withErrors(['email' => 'The lead has no primary email.']);
        }

        // Guardamos los adjuntos en el disco "public" (mismo patrón que las notas),
        // así el timeline los puede previsualizar/descargar con /storage/{path}.
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store("sponsorship/lead-files/{$lead->id}", 'public');
                $attachments[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        \App\Jobs\Sponsorship\SendLeadOutreachEmailJob::dispatch(
            leadId: $lead->id,
            senderUserId: auth()->id(),
            subjectLine: $validated['subject'],
            bodyText: $validated['body'],
            isContract: (bool) ($validated['is_contract'] ?? false),
            attachments: $attachments,
        );

        return back()->with('success', 'Email queued for sending.');
    }

    // ─────────────────────────── Activities / Timeline ───────────────────────────

    public function addActivity(Request $request, Lead $lead)
    {
        $this->authorizeSee($lead);

        $validated = $request->validate([
            'type'                => ['required', Rule::in(array_keys(LeadActivity::TYPES))],
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'scheduled_at'        => 'nullable|date',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'is_contract'         => 'nullable|boolean',
            'files'               => 'nullable|array',
            'files.*'             => 'file|max:20480',
        ]);

        $isScheduled = !empty($validated['scheduled_at']);

        $activity = LeadActivity::create([
            'lead_id'             => $lead->id,
            'created_by_user_id'  => auth()->id(),
            'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? auth()->id(),
            'type'                => $validated['type'],
            'title'               => $validated['title'],
            'description'         => $validated['description'] ?? null,
            'scheduled_at'        => $validated['scheduled_at'] ?? null,
            'completed_at'        => $isScheduled ? null : now(),
            'status'              => $isScheduled ? 'pending' : 'completed',
            'is_contract'         => ($validated['type'] === 'email' && !empty($validated['is_contract'])),
        ]);

        // Guardar archivos adjuntos
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("sponsorship/lead-files/{$lead->id}", 'public');
                LeadActivityFile::create([
                    'activity_id' => $activity->id,
                    'file_path'   => $path,
                    'file_name'   => $file->getClientOriginalName(),
                    'size'        => $file->getSize(),
                    'mime_type'   => $file->getMimeType(),
                ]);
            }
        }

        // Si la actividad queda completada inmediatamente, aplicar reglas de status
        if (!$isScheduled) {
            $this->applyActivityRules($lead->fresh(), $activity);
        }

        return back()->with('success', 'Activity created.');
    }

    public function completeActivity(LeadActivity $activity)
    {
        $this->authorizeSee($activity->lead);

        $activity->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        $this->applyActivityRules($activity->lead->fresh(), $activity->fresh());

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Activity completed.');
    }

    public function cancelActivity(LeadActivity $activity)
    {
        $this->authorizeSee($activity->lead);
        $activity->update(['status' => 'cancelled']);

        if (request()->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Activity cancelled.');
    }

    public function notCompletedActivity(LeadActivity $activity)
    {
        $this->authorizeSee($activity->lead);
        $activity->update(['status' => 'not_completed']);

        if (request()->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Activity marked as not completed.');
    }

    public function destroyActivity(LeadActivity $activity)
    {
        $this->authorizeSee($activity->lead);
        $activity->delete();
        return back()->with('success', 'Activity deleted.');
    }

    public function updateActivity(Request $request, LeadActivity $activity)
    {
        $this->authorizeSee($activity->lead);

        // Tipos editables manualmente. Email + auto-types (status_change/assignment/system) no son editables aquí.
        $editableTypes = ['note', 'call', 'meeting'];
        if (!in_array($activity->type, $editableTypes, true)) {
            abort(403, 'This activity type cannot be edited.');
        }

        $validated = $request->validate([
            'title'               => 'nullable|string|max:255',
            'description'         => 'nullable|string',
            'scheduled_at'        => 'nullable|date',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'files'               => 'nullable|array',
            'files.*'             => 'file|max:20480',
        ]);

        $updates = [
            'edited_by_user_id' => auth()->id(),
            'edited_at'         => now(),
        ];

        if ($activity->type === 'note') {
            // Las notas exigen description (mantenemos el contrato anterior).
            if (empty(trim($validated['description'] ?? ''))) {
                throw ValidationException::withMessages(['description' => 'Description is required for notes.']);
            }
            $updates['title']       = $validated['title'] ?: 'Note';
            $updates['description'] = $validated['description'];
        } else {
            // call / meeting
            if (empty(trim($validated['title'] ?? ''))) {
                throw ValidationException::withMessages(['title' => 'Title is required.']);
            }
            $updates['title']               = $validated['title'];
            $updates['description']         = $validated['description'] ?? null;
            $updates['scheduled_at']        = $validated['scheduled_at'] ?? null;
            $updates['assigned_to_user_id'] = $validated['assigned_to_user_id'] ?? $activity->assigned_to_user_id;
        }

        $activity->update($updates);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("sponsorship/lead-files/{$activity->lead_id}", 'public');
                LeadActivityFile::create([
                    'activity_id' => $activity->id,
                    'file_path'   => $path,
                    'file_name'   => $file->getClientOriginalName(),
                    'size'        => $file->getSize(),
                    'mime_type'   => $file->getMimeType(),
                ]);
            }
        }

        return back()->with('success', 'Activity updated.');
    }

    /**
     * Aplica las reglas automáticas de transición de status del lead:
     *   - Al completar cualquier actividad tipo call/email/meeting, si lead.status === 'nuevo' → 'contactado'
     *   - Al completar actividad tipo email con is_contract = true → lead.status = 'contrato'
     * También actualiza last_contacted_at.
     */
    private function applyActivityRules(Lead $lead, LeadActivity $activity): void
    {
        if ($activity->status !== 'completed') return;

        $updates = [];

        if (in_array($activity->type, ['call', 'email', 'meeting'])) {
            $updates['last_contacted_at'] = now();
        }

        if ($activity->type === 'email' && $activity->is_contract) {
            $updates['status'] = 'contrato';
        } elseif ($lead->status === 'nuevo' && in_array($activity->type, ['call', 'email', 'meeting'])) {
            $updates['status'] = 'contactado';
        }

        if ($updates) {
            $lead->update($updates);
        }
    }

    // ─────────────────────────── Calendar ───────────────────────────

    public function calendar()
    {
        $advisors = User::where('role', 'sponsorship')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'sponsorship_type']);

        return Inertia::render('Admin/Sponsorship/Calendar', [
            'advisors'      => $advisors,
            'isLider'       => $this->isLider(),
            'activityTypes' => LeadActivity::TYPES,
        ]);
    }

    public function calendarEvents(Request $request)
    {
        $query = LeadActivity::whereNotNull('scheduled_at')
            ->with([
                'lead:id,first_name,last_name,company_id',
                'lead.company:id,name',
                'assignedTo:id,first_name,last_name',
                'creator:id,first_name,last_name',
            ]);

        if (!$this->isLider()) {
            // Asesor: ve solo sus actividades (creadas por él o asignadas a él)
            $uid = auth()->id();
            $query->where(function ($q) use ($uid) {
                $q->where('assigned_to_user_id', $uid)
                  ->orWhere('created_by_user_id', $uid);
            });
        } elseif ($request->filled('advisor')) {
            $query->where('assigned_to_user_id', $request->advisor);
        }

        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('scheduled_at', [$request->start, $request->end]);
        }

        $events = $query->get()->map(fn($a) => [
            'id'          => $a->id,
            'title'       => $a->title,
            'start'       => $a->scheduled_at?->toIso8601String(),
            'type'        => $a->type,
            'status'      => $a->status,
            'is_contract' => $a->is_contract,
            'lead_id'     => $a->lead_id,
            'lead_name'   => $a->lead ? "{$a->lead->first_name} {$a->lead->last_name}" : null,
            'company'     => $a->lead?->company?->name,
            'advisor'     => $a->assignedTo ? "{$a->assignedTo->first_name} {$a->assignedTo->last_name}" : null,
            'creator'     => $a->creator ? "{$a->creator->first_name} {$a->creator->last_name}" : null,
            'description' => $a->description,
        ]);

        return response()->json($events);
    }
}
