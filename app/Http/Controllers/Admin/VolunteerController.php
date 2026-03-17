<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VolunteersExport;
use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\User;
use App\Models\VolunteerSchedule;
use App\Services\TwilioService;
use App\Support\InstagramSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class VolunteerController extends Controller
{
    public function __construct(
        protected TwilioService $twilioService,
    ) {}

    // ──────────────────────────────────────────────
    //  CRUD
    // ──────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $query = User::where('role', 'volunteer')
            ->with(['volunteerProfile', 'eventsAsStaff', 'volunteerSchedules.eventDay', 'eventPasses']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('event_id')) {
            $eventId = $request->event_id;
            $query->whereHas('eventsAsStaff', fn ($q) => $q->where('events.id', $eventId));
        }

        $volunteers = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $events = Event::where('status', 'active')
            ->orderBy('start_date')
            ->get(['id', 'name']);

        // Contar pendientes de onboarding email
        $pendingEmailCount = User::where('role', 'volunteer')
            ->where('status', 'pending')
            ->whereNull('welcome_email_sent_at')
            ->whereHas('eventsAsStaff')
            ->count();

        $pendingSmsCount = User::where('role', 'volunteer')
            ->where('status', 'pending')
            ->whereNotNull('phone')
            ->where('phone', 'like', '+%')
            ->whereNull('sms_sent_at')
            ->whereHas('eventsAsStaff')
            ->count();

        return Inertia::render('Admin/Volunteers/Index', [
            'volunteers'        => $volunteers,
            'filters'           => $request->only(['search', 'status', 'event_id']),
            'events'            => $events,
            'pendingEmailCount' => $pendingEmailCount,
            'pendingSmsCount'   => $pendingSmsCount,
        ]);
    }

    public function show(User $volunteer): Response
    {
        $this->authorizeVolunteer($volunteer);

        $volunteer->load([
            'volunteerProfile',
            'eventsAsStaff.eventDays',
            'volunteerSchedules.eventDay',
            'volunteerSchedules.event',
            'eventPasses',
            'communicationLogs' => fn ($q) => $q->latest()->limit(20),
        ]);

        $events = Event::where('status', '!=', 'cancelled')
            ->with('eventDays')
            ->orderBy('start_date')
            ->get();

        return Inertia::render('Admin/Volunteers/Show', [
            'volunteer' => $volunteer,
            'events'    => $events,
        ]);
    }

    public function create(): Response
    {
        $events = Event::where('status', '!=', 'cancelled')
            ->with('eventDays')
            ->orderBy('start_date')
            ->get();

        return Inertia::render('Admin/Volunteers/Create', [
            'events' => $events,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'             => 'required|string|max:255',
            'last_name'              => 'required|string|max:255',
            'email'                  => 'required|email|unique:users,email',
            'phone'                  => 'nullable|string|unique:users,phone',
            'age'                    => 'nullable|integer|min:18|max:80',
            'gender'                 => 'nullable|in:female,male,non_binary',
            'location'               => 'nullable|string|max:255',
            'instagram'              => 'nullable|string|max:255',
            'tshirt_size'            => 'nullable|in:XS,S,M,L,XL,XXL',
            'experience'             => 'nullable|in:none,some,experienced',
            'comfortable_fast_paced' => 'nullable|in:multitask,structured',
            'full_availability'      => 'nullable|in:yes,no,partially',
            'contribution'           => 'nullable|string|max:1000',
            'resume_link'            => 'nullable|url|max:500',
            'notes'                  => 'nullable|string|max:2000',
            'event_id'               => 'nullable|exists:events,id',
        ], [
            'email.unique' => 'Este correo ya está registrado en el sistema.',
            'phone.unique' => 'Este teléfono ya está registrado en el sistema.',
        ]);

        $this->sanitizeInstagram($validated);

        try {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'email'      => $validated['email'],
                'phone'      => $validated['phone'] ?? null,
                'role'       => 'volunteer',
                'status'     => 'applicant',
                'password'   => Hash::make('runway7'),
            ]);

            $profileData = array_filter([
                'age'                    => $validated['age'] ?? null,
                'gender'                 => $validated['gender'] ?? null,
                'tshirt_size'            => $validated['tshirt_size'] ?? null,
                'experience'             => $validated['experience'] ?? null,
                'comfortable_fast_paced' => $validated['comfortable_fast_paced'] ?? null,
                'full_availability'      => $validated['full_availability'] ?? null,
                'contribution'           => $validated['contribution'] ?? null,
                'resume_link'            => $validated['resume_link'] ?? null,
                'instagram'              => $validated['instagram'] ?? null,
                'location'               => $validated['location'] ?? null,
                'notes'                  => $validated['notes'] ?? null,
            ], fn ($v) => $v !== null);

            if (!empty($profileData)) {
                $user->volunteerProfile()->create($profileData);
            }

            if (!empty($validated['event_id'])) {
                $user->eventsAsStaff()->attach($validated['event_id'], [
                    'assigned_role' => 'volunteer',
                    'status'        => 'assigned',
                ]);
            }
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $message = str_contains($e->getMessage(), 'phone')
                ? 'Este teléfono ya está registrado en el sistema.'
                : 'Este correo ya está registrado en el sistema.';
            return back()->withInput()->with('error', $message);
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Ocurrió un error inesperado al crear el voluntario. Por favor intenta de nuevo.');
        }

        return redirect()->route('admin.volunteers.show', $user)
            ->with('success', 'Voluntario creado correctamente.');
    }

    public function edit(User $volunteer): Response
    {
        $this->authorizeVolunteer($volunteer);

        $volunteer->load([
            'volunteerProfile',
            'eventsAsStaff.eventDays',
            'volunteerSchedules.eventDay',
        ]);

        $events = Event::where('status', '!=', 'cancelled')
            ->with('eventDays')
            ->orderBy('start_date')
            ->get();

        return Inertia::render('Admin/Volunteers/Edit', [
            'volunteer' => $volunteer,
            'events'    => $events,
        ]);
    }

    public function update(Request $request, User $volunteer)
    {
        $this->authorizeVolunteer($volunteer);

        $validated = $request->validate([
            'first_name'             => 'required|string|max:255',
            'last_name'              => 'nullable|string|max:255',
            'email'                  => "required|email|unique:users,email,{$volunteer->id}",
            'phone'                  => "nullable|string|unique:users,phone,{$volunteer->id}",
            'age'                    => 'nullable|integer|min:18|max:80',
            'gender'                 => 'nullable|in:female,male,non_binary',
            'location'               => 'nullable|string|max:255',
            'instagram'              => 'nullable|string|max:255',
            'tshirt_size'            => 'nullable|in:XS,S,M,L,XL,XXL',
            'experience'             => 'nullable|in:none,some,experienced',
            'comfortable_fast_paced' => 'nullable|in:multitask,structured',
            'full_availability'      => 'nullable|in:yes,no,partially',
            'contribution'           => 'nullable|string|max:1000',
            'resume_link'            => 'nullable|url|max:500',
            'notes'                  => 'nullable|string|max:2000',
        ], [
            'email.unique' => 'Este correo ya está registrado en el sistema.',
            'phone.unique' => 'Este teléfono ya está registrado en el sistema.',
        ]);

        $this->sanitizeInstagram($validated);

        try {
            $volunteer->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'] ?? '',
                'email'      => $validated['email'],
                'phone'      => $validated['phone'] ?? null,
            ]);

            $profileFields = [
                'age', 'gender', 'tshirt_size', 'experience', 'comfortable_fast_paced',
                'full_availability', 'contribution', 'resume_link', 'instagram', 'location', 'notes',
            ];

            $profileData = collect($validated)->only($profileFields)->toArray();

            $volunteer->volunteerProfile()->updateOrCreate(
                ['user_id' => $volunteer->id],
                $profileData,
            );
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Ocurrió un error inesperado al actualizar el voluntario. Por favor intenta de nuevo.');
        }

        return redirect()->route('admin.volunteers.show', $volunteer)
            ->with('success', 'Voluntario actualizado correctamente.');
    }

    public function destroy(User $volunteer)
    {
        $this->authorizeVolunteer($volunteer);

        DB::transaction(function () use ($volunteer) {
            $volunteer->volunteerSchedules()->delete();
            $volunteer->volunteerProfile?->delete();
            $volunteer->eventsAsStaff()->detach();
            DB::table('event_passes')->where('user_id', $volunteer->id)->delete();
            DB::table('communication_logs')->where('user_id', $volunteer->id)->delete();
            $volunteer->forceDelete();
        });

        return redirect()->route('admin.volunteers.index')
            ->with('success', 'Voluntario eliminado correctamente.');
    }

    // ──────────────────────────────────────────────
    //  Status
    // ──────────────────────────────────────────────

    public function updateStatus(Request $request, User $volunteer)
    {
        $this->authorizeVolunteer($volunteer);

        $request->validate([
            'status' => 'required|in:applicant,pending,active,inactive',
        ]);

        $newStatus = $request->status;

        // Validar que tenga evento y horarios para pasar a pendiente
        if ($newStatus === 'pending') {
            if ($volunteer->eventsAsStaff()->count() === 0) {
                return back()->with('error', 'No se puede cambiar a Pendiente: el voluntario no tiene eventos asignados.');
            }
            if ($volunteer->volunteerSchedules()->count() === 0) {
                return back()->with('error', 'No se puede cambiar a Pendiente: el voluntario no tiene horarios asignados.');
            }
        }

        // Si se pone inactive, rechazar en todos los eventos y cancelar pases
        if ($newStatus === 'inactive') {
            $eventIds = $volunteer->eventsAsStaff->pluck('id')->toArray();
            $volunteer->eventsAsStaff()->updateExistingPivot(
                $eventIds,
                ['status' => 'rejected'],
            );
            EventPass::where('user_id', $volunteer->id)
                ->whereIn('event_id', $eventIds)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);
        }

        $volunteer->update(['status' => $newStatus]);

        $labels = ['applicant' => 'Aplicante', 'pending' => 'Pendiente', 'active' => 'Activo', 'inactive' => 'Inactivo'];

        return back()->with('success', "Estado cambiado a {$labels[$newStatus]}.");
    }

    // ──────────────────────────────────────────────
    //  Event management
    // ──────────────────────────────────────────────

    public function assignEvent(Request $request, User $volunteer)
    {
        $this->authorizeVolunteer($volunteer);

        $request->validate([
            'event_id' => 'required|exists:events,id',
            'area'     => 'nullable|string|max:255',
        ]);

        $eventId = (int) $request->event_id;

        if ($volunteer->eventsAsStaff()->where('events.id', $eventId)->exists()) {
            return back()->with('error', 'El voluntario ya está asignado a este evento.');
        }

        $volunteer->eventsAsStaff()->attach($eventId, [
            'assigned_role' => 'volunteer',
            'status'        => 'assigned',
            'area'          => $request->area,
        ]);

        // Generar pase de acceso
        EventPass::create([
            'event_id'     => $eventId,
            'user_id'      => $volunteer->id,
            'issued_by'    => auth()->id(),
            'qr_code'      => EventPass::generateQrCode(),
            'pass_type'    => 'volunteer',
            'holder_name'  => $volunteer->full_name,
            'holder_email' => $volunteer->email,
            'valid_days'   => null,
            'issued_at'    => now(),
            'status'       => 'active',
        ]);

        return back()->with('success', 'Evento asignado correctamente.');
    }

    public function updateEventStatus(Request $request, User $volunteer, Event $event)
    {
        $this->authorizeVolunteer($volunteer);

        $request->validate([
            'status' => 'required|in:assigned,checked_in,no_show,rejected',
        ]);

        $newStatus = $request->status;

        $previousStatus = DB::table('event_staff')
            ->where('user_id', $volunteer->id)
            ->where('event_id', $event->id)
            ->value('status');

        // Si se rechaza, cancelar pase
        if ($newStatus === 'rejected' && $previousStatus !== 'rejected') {
            EventPass::where('user_id', $volunteer->id)
                ->where('event_id', $event->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);
        }

        // Si se reactiva desde rejected, restaurar pase
        if ($previousStatus === 'rejected' && $newStatus !== 'rejected') {
            EventPass::where('user_id', $volunteer->id)
                ->where('event_id', $event->id)
                ->where('status', 'cancelled')
                ->update(['status' => 'active']);
        }

        DB::table('event_staff')
            ->where('user_id', $volunteer->id)
            ->where('event_id', $event->id)
            ->update(['status' => $newStatus]);

        $labels = [
            'assigned'   => 'Agendado',
            'checked_in' => 'Check-in',
            'no_show'    => 'No se presentó',
            'rejected'   => 'Rechazado',
        ];

        return back()->with('success', "Estado actualizado a {$labels[$newStatus]}.");
    }

    public function removeEvent(User $volunteer, Event $event)
    {
        $this->authorizeVolunteer($volunteer);

        // Eliminar schedules de este evento
        $volunteer->volunteerSchedules()->where('event_id', $event->id)->delete();

        // Cancelar pase
        EventPass::where('user_id', $volunteer->id)->where('event_id', $event->id)->update(['status' => 'cancelled']);

        // Desvincular del evento
        $volunteer->eventsAsStaff()->detach($event->id);

        return back()->with('success', 'Evento removido correctamente.');
    }

    // ──────────────────────────────────────────────
    //  Schedules (día + horario)
    // ──────────────────────────────────────────────

    public function addSchedule(Request $request, User $volunteer)
    {
        $this->authorizeVolunteer($volunteer);

        $request->validate([
            'event_id'     => 'required|exists:events,id',
            'event_day_id' => 'required|exists:event_days,id',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i|after:start_time',
        ]);

        $exists = VolunteerSchedule::where('user_id', $volunteer->id)
            ->where('event_id', $request->event_id)
            ->where('event_day_id', $request->event_day_id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Ya existe un horario para este día. Elimina el existente primero.');
        }

        VolunteerSchedule::create([
            'user_id'      => $volunteer->id,
            'event_id'     => $request->event_id,
            'event_day_id' => $request->event_day_id,
            'start_time'   => $request->start_time,
            'end_time'     => $request->end_time,
        ]);

        return back()->with('success', 'Horario agregado correctamente.');
    }

    public function updateEventArea(Request $request, User $volunteer, Event $event)
    {
        $this->authorizeVolunteer($volunteer);

        $request->validate(['area' => 'nullable|string|max:255']);

        $volunteer->eventsAsStaff()->updateExistingPivot($event->id, [
            'area' => $request->area,
        ]);

        return back()->with('success', 'Área actualizada correctamente.');
    }

    public function removeSchedule(User $volunteer, VolunteerSchedule $schedule)
    {
        $this->authorizeVolunteer($volunteer);

        if ($schedule->user_id !== $volunteer->id) {
            abort(403);
        }

        $schedule->delete();

        return back()->with('success', 'Horario eliminado correctamente.');
    }

    // ──────────────────────────────────────────────
    //  Onboarding Email
    // ──────────────────────────────────────────────

    public function sendOnboardingEmail(Request $request, User $volunteer)
    {
        $this->authorizeVolunteer($volunteer);

        if ($volunteer->status !== 'pending') {
            return back()->with('error', 'Solo se puede enviar onboarding a voluntarios con estado Pendiente.');
        }

        $log = CommunicationLog::create([
            'user_id' => $volunteer->id,
            'sent_by' => $request->user()->id,
            'type'    => 'email',
            'channel' => 'onboarding_email',
            'status'  => 'queued',
        ]);

        try {
            \App\Jobs\SendVolunteerOnboardingJob::dispatch(
                userId:  $volunteer->id,
                sentBy:  $request->user()->id,
                logId:   $log->id,
            );
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return back()->with('error', "Error al enviar email: {$e->getMessage()}");
        }

        if ($volunteer->status === 'pending') {
            $volunteer->update(['status' => 'active']);
        }

        return back()->with('success', "Email de onboarding encolado para {$volunteer->full_name}.");
    }

    public function sendBulkOnboardingEmails(Request $request)
    {
        $volunteers = User::where('role', 'volunteer')
            ->where('status', 'pending')
            ->whereNull('welcome_email_sent_at')
            ->whereHas('eventsAsStaff')
            ->get();

        $count = 0;
        foreach ($volunteers as $volunteer) {
            $log = CommunicationLog::create([
                'user_id' => $volunteer->id,
                'sent_by' => $request->user()->id,
                'type'    => 'email',
                'channel' => 'onboarding_email',
                'status'  => 'queued',
            ]);

            try {
                \App\Jobs\SendVolunteerOnboardingJob::dispatch(
                    userId:  $volunteer->id,
                    sentBy:  $request->user()->id,
                    logId:   $log->id,
                );
            } catch (\Throwable $e) {
                $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                continue;
            }

            if ($volunteer->status === 'pending') {
                $volunteer->update(['status' => 'active']);
            }

            $count++;
        }

        return back()->with('success', "Email de onboarding encolado para {$count} voluntarios.");
    }

    // ──────────────────────────────────────────────
    //  Onboarding SMS
    // ──────────────────────────────────────────────

    public function sendOnboardingSms(Request $request, User $volunteer)
    {
        $this->authorizeVolunteer($volunteer);

        if ($volunteer->status !== 'pending') {
            return back()->with('error', 'Solo se puede enviar SMS a voluntarios con estado Pendiente.');
        }

        if (!$volunteer->phone) {
            return back()->with('error', "{$volunteer->full_name} no tiene número de teléfono registrado.");
        }

        if (!str_starts_with($volunteer->phone, '+')) {
            return back()->with('error', "El teléfono de {$volunteer->full_name} ({$volunteer->phone}) debe incluir código de país (ej: +1...).");
        }

        $balance = $this->twilioService->getBalance();
        if (!$balance || (float) str_replace(',', '', $balance['balance']) <= 0) {
            $msg = $balance ? "Saldo insuficiente en Twilio ({$balance['balance']} {$balance['currency']})." : 'Saldo insuficiente en Twilio.';
            return back()->with('error', "$msg Recarga para poder enviar SMS.");
        }

        $log = CommunicationLog::create([
            'user_id' => $volunteer->id,
            'sent_by' => $request->user()->id,
            'type'    => 'sms',
            'channel' => 'onboarding_sms',
            'status'  => 'queued',
        ]);

        try {
            \App\Jobs\SendVolunteerOnboardingSmsJob::dispatch(
                userId:  $volunteer->id,
                sentBy:  $request->user()->id,
                logId:   $log->id,
            );
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return back()->with('error', "Error al enviar SMS: {$e->getMessage()}");
        }

        if ($volunteer->status === 'pending') {
            $volunteer->update(['status' => 'active']);
        }

        return back()->with('success', "SMS de onboarding encolado para {$volunteer->full_name}.");
    }

    public function sendBulkOnboardingSms(Request $request)
    {
        $balance = $this->twilioService->getBalance();
        if (!$balance || (float) str_replace(',', '', $balance['balance']) <= 0) {
            $msg = $balance ? "Saldo insuficiente en Twilio ({$balance['balance']} {$balance['currency']})." : 'Saldo insuficiente en Twilio.';
            return back()->with('error', "$msg Recarga para poder enviar SMS.");
        }

        $volunteers = User::where('role', 'volunteer')
            ->where('status', 'pending')
            ->whereNotNull('phone')
            ->where('phone', 'like', '+%')
            ->whereNull('sms_sent_at')
            ->whereHas('eventsAsStaff')
            ->get();

        $count = 0;
        foreach ($volunteers as $volunteer) {
            $log = CommunicationLog::create([
                'user_id' => $volunteer->id,
                'sent_by' => $request->user()->id,
                'type'    => 'sms',
                'channel' => 'onboarding_sms',
                'status'  => 'queued',
            ]);

            try {
                \App\Jobs\SendVolunteerOnboardingSmsJob::dispatch(
                    userId:  $volunteer->id,
                    sentBy:  $request->user()->id,
                    logId:   $log->id,
                );
            } catch (\Throwable $e) {
                $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                continue;
            }

            if ($volunteer->status === 'pending') {
                $volunteer->update(['status' => 'active']);
            }

            $count++;
        }

        return back()->with('success', "SMS de onboarding encolado para {$count} voluntarios.");
    }

    // ──────────────────────────────────────────────
    //  Export / Import
    // ──────────────────────────────────────────────

    public function exportVolunteers(Request $request)
    {
        $filename = 'voluntarios_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new VolunteersExport(
            search:  $request->input('search'),
            status:  $request->input('status'),
            eventId: $request->input('event_id'),
        ), $filename);
    }

    public function importVolunteers(Request $request)
    {
        $request->validate([
            'file'     => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'event_id' => 'nullable|exists:events,id',
        ]);

        $eventId = $request->filled('event_id') ? (int) $request->event_id : null;
        $import = new \App\Imports\VolunteersImport(globalEventId: $eventId);
        Excel::import($import, $request->file('file'));

        $s = $import->summary;
        $msg = "Importación completada: {$s['created']} creados, {$s['updated']} actualizados.";

        if (!empty($s['errors'])) {
            $msg .= ' ' . count($s['errors']) . ' errores.';
        }

        return back()->with('success', $msg)->with('importSummary', $s);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function authorizeVolunteer(User $volunteer): void
    {
        if ($volunteer->role !== 'volunteer') {
            abort(403, 'Este usuario no es un voluntario.');
        }
    }

    private function sanitizeInstagram(array &$data): void
    {
        $data['instagram'] = InstagramSanitizer::sanitize($data['instagram'] ?? null);
    }
}
