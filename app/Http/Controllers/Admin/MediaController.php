<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Jobs\SendMediaAssistantOnboardingJob;
use App\Jobs\SendMediaOnboardingJob;
use App\Jobs\SendMediaOnboardingSmsJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\MediaAssistant;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLog,
        protected TwilioService $twilioService,
    ) {}

    private function authorizeMedia(User $media): void
    {
        abort_unless($media->role === 'media', 404);
    }

    // ──────────────────────────────────────────────
    //  Index
    // ──────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $query = User::media()->with([
            'mediaProfile',
            'eventsAsMedia',
            'communicationLogs' => fn($q) => $q->whereIn('channel', ['media_registration', 'media_onboarding', 'media_onboarding_sms'])->with('sender')->orderByDesc('created_at'),
        ]);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'ilike', "%{$s}%")
                  ->orWhere('last_name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
                  ->orWhere('phone', 'ilike', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('event_id')) {
            $query->whereHas('eventsAsMedia', fn($q) => $q->where('events.id', $request->event_id));
        }

        if ($request->filled('category')) {
            $query->whereHas('mediaProfile', fn($q) => $q->where('category', $request->category));
        }

        if ($request->filled('payment_status')) {
            $query->whereHas('eventsAsMedia', fn($q) => $q->where('event_media.payment_status', $request->payment_status));
        }

        $query->orderBy('created_at', 'desc');

        $perPage = in_array((int) $request->input('per_page'), [20, 50, 100, 200, 500]) ? (int) $request->input('per_page') : 20;
        $mediaUsers = $query->paginate($perPage)->withQueryString();

        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        $pendingEmailCount = User::media()
            ->where('status', 'pending')
            ->whereNull('welcome_email_sent_at')
            ->whereHas('eventsAsMedia')
            ->count();

        $pendingSmsCount = User::media()
            ->where('status', 'pending')
            ->whereNotNull('phone')
            ->where('phone', 'like', '+%')
            ->whereNull('sms_sent_at')
            ->whereHas('eventsAsMedia')
            ->count();

        return Inertia::render('Admin/Media/Index', [
            'mediaUsers'         => $mediaUsers,
            'events'             => $events,
            'filters'            => $request->only(['search', 'status', 'event_id', 'category', 'payment_status', 'per_page']),
            'pendingEmailCount'  => $pendingEmailCount,
            'pendingSmsCount'    => $pendingSmsCount,
            'twilioBalance'      => $this->twilioService->getBalance(),
        ]);
    }

    // ──────────────────────────────────────────────
    //  Show
    // ──────────────────────────────────────────────

    public function show(User $media): Response
    {
        $this->authorizeMedia($media);

        $media->load([
            'mediaProfile',
            'eventsAsMedia.eventDays',
            'mediaAssistants.event',
            'eventPasses',
            'communicationLogs' => fn($q) => $q->whereIn('channel', ['media_registration', 'media_onboarding', 'media_onboarding_sms'])->with('sender')->orderByDesc('created_at')->limit(20),
        ]);

        $passMap = $media->eventPasses->keyBy('event_id');

        $kitsConfig   = config('media_kits.kits');
        $addonsConfig = config('media_kits.addons');

        return Inertia::render('Admin/Media/Show', [
            'media' => array_merge($media->toArray(), [
                'media_profile' => $media->mediaProfile,
                'events' => $media->eventsAsMedia?->map(function ($event) use ($passMap, $kitsConfig, $addonsConfig) {
                    $addonKeys = is_array($event->pivot->addons)
                        ? $event->pivot->addons
                        : (json_decode($event->pivot->addons ?? '[]', true) ?: []);

                    $purchase = $event->pivot->kit_type ? [
                        'kit_type'             => $event->pivot->kit_type,
                        'kit_name'             => $kitsConfig[$event->pivot->kit_type]['name'] ?? $event->pivot->kit_type,
                        'addons'               => array_map(fn($k) => [
                            'key'   => $k,
                            'name'  => $addonsConfig[$k]['name'] ?? $k,
                            'price' => (float) ($addonsConfig[$k]['price'] ?? 0),
                        ], $addonKeys),
                        'payment_status'       => $event->pivot->payment_status,
                        'total_amount'         => $event->pivot->total_amount !== null ? (float) $event->pivot->total_amount : null,
                        'shopify_order_number' => $event->pivot->shopify_order_number,
                        'paid_at'              => $event->pivot->paid_at,
                    ] : null;

                    return [
                        'id'             => $event->id,
                        'name'           => $event->name,
                        'status'         => $event->status,
                        'media_status'   => $event->pivot->status,
                        'payment_status' => $event->pivot->payment_status,
                        'checked_in_at'  => $event->pivot->checked_in_at,
                        'days'           => $event->eventDays->map(fn($d) => ['id' => $d->id, 'label' => $d->label, 'date' => $d->date?->format('Y-m-d')])->toArray(),
                        'pass'           => $passMap->has($event->id) ? (function () use ($passMap, $event) {
                            $p = $passMap[$event->id];
                            return [
                                'qr_code'         => $p->qr_code,
                                'status'          => $p->status,
                                'pass_type'       => $p->pass_type,
                                'holder_name'     => $p->holder_name,
                                'is_preferential' => (bool) $p->is_preferential,
                            ];
                        })() : null,
                        'purchase'       => $purchase,
                    ];
                })->toArray(),
                'assistants' => $media->mediaAssistants->map(fn($a) => [
                    'id'          => $a->id,
                    'full_name'   => $a->full_name,
                    'document_id' => $a->document_id,
                    'phone'       => $a->phone,
                    'email'       => $a->email,
                    'event_id'    => $a->event_id,
                    'event_name'  => $a->event?->name,
                ])->toArray(),
                'communication_logs' => $media->communicationLogs->toArray(),
            ]),
            'events' => Event::orderBy('start_date', 'desc')->get(['id', 'name']),
        ]);
    }

    // ──────────────────────────────────────────────
    //  Create + Store
    // ──────────────────────────────────────────────

    public function create(): Response
    {
        return Inertia::render('Admin/Media/Create', [
            'events' => Event::whereIn('status', ['published', 'active', 'draft'])
                ->orderBy('start_date', 'desc')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'nullable|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'phone'         => 'nullable|string',
            'category'      => 'required|in:videographer,photographer',
            'portfolio_url' => 'nullable|url|max:500',
            'instagram'     => 'nullable|string|max:255',
            'location'      => 'nullable|string|max:255',
            'will_travel'   => 'nullable|in:yes,no',
            'importance'    => 'nullable|integer|in:1,2,3',
            'max_assistants'=> 'nullable|integer|min:0',
            'notes'         => 'nullable|string',
            'event_id'      => 'nullable|exists:events,id',
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name ?? '',
                'email'      => $request->email,
                'phone'      => $request->phone,
                'role'       => 'media',
                'status'     => 'applicant',
                'password'   => Hash::make('runway7'),
            ]);

            $user->mediaProfile()->create([
                'category'       => $request->category,
                'portfolio_url'  => $request->portfolio_url,
                'instagram'      => $request->instagram,
                'location'       => $request->location,
                'will_travel'    => $request->will_travel ?? 'yes',
                'importance'     => $request->importance ?? 2,
                'max_assistants' => $request->max_assistants ?? 0,
                'notes'          => $request->notes,
            ]);

            if ($request->filled('event_id')) {
                $user->eventsAsMedia()->attach($request->event_id, [
                    'status'         => 'assigned',
                    'payment_status' => 'manual',
                ]);

                EventPass::create([
                    'event_id'     => $request->event_id,
                    'user_id'      => $user->id,
                    'issued_by'    => auth()->id(),
                    'qr_code'      => EventPass::generateQrCode(),
                    'pass_type'    => 'media',
                    'holder_name'  => $user->full_name,
                    'holder_email' => $user->email,
                    'issued_at'    => now(),
                    'status'       => 'active',
                ]);
            }

            return $user;
        });

        return redirect()->route('admin.media.show', $media = $user)
            ->with('success', 'Media creado exitosamente.');
    }

    // ──────────────────────────────────────────────
    //  Edit + Update
    // ──────────────────────────────────────────────

    public function edit(User $media): Response
    {
        $this->authorizeMedia($media);

        $media->load(['mediaProfile', 'eventsAsMedia', 'mediaAssistants.event']);

        return Inertia::render('Admin/Media/Edit', [
            'media'  => $media,
            'events' => Event::whereIn('status', ['published', 'active', 'draft'])
                ->orderBy('start_date', 'desc')
                ->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $media)
    {
        $this->authorizeMedia($media);

        $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'nullable|string|max:255',
            'email'         => "required|email|unique:users,email,{$media->id}",
            'phone'         => 'nullable|string',
            'status'        => 'nullable|in:applicant,pending,active,inactive',
            'category'      => 'required|in:videographer,photographer',
            'portfolio_url' => 'nullable|url|max:500',
            'instagram'     => 'nullable|string|max:255',
            'location'      => 'nullable|string|max:255',
            'will_travel'   => 'nullable|in:yes,no',
            'importance'    => 'nullable|integer|in:1,2,3',
            'max_assistants'=> 'nullable|integer|min:0',
            'notes'         => 'nullable|string',
            'media_link_1'  => 'nullable|url|max:500',
            'media_link_2'  => 'nullable|url|max:500',
            'media_link_3'  => 'nullable|url|max:500',
        ]);

        $media->update($request->only(['first_name', 'last_name', 'email', 'phone', 'status']));

        $media->mediaProfile()->updateOrCreate(
            ['user_id' => $media->id],
            $request->only([
                'category', 'portfolio_url', 'instagram', 'location', 'will_travel',
                'importance', 'max_assistants', 'notes',
                'media_link_1', 'media_link_2', 'media_link_3',
            ]),
        );

        return redirect()->route('admin.media.show', $media)
            ->with('success', 'Media actualizado exitosamente.');
    }

    // ──────────────────────────────────────────────
    //  Delete
    // ──────────────────────────────────────────────

    // ──────────────────────────────────────────────
    //  Event management
    // ──────────────────────────────────────────────

    public function assignEvent(Request $request, User $media)
    {
        $this->authorizeMedia($media);

        $request->validate(['event_id' => 'required|exists:events,id']);

        $eventId = (int) $request->event_id;

        if ($media->eventsAsMedia()->where('events.id', $eventId)->exists()) {
            return back()->with('error', 'Ya está asignado a este evento.');
        }

        $media->eventsAsMedia()->attach($eventId, [
            'status'         => 'assigned',
            'payment_status' => 'manual',
        ]);

        EventPass::create([
            'event_id'     => $eventId,
            'user_id'      => $media->id,
            'issued_by'    => auth()->id(),
            'qr_code'      => EventPass::generateQrCode(),
            'pass_type'    => 'media',
            'holder_name'  => $media->full_name,
            'holder_email' => $media->email,
            'issued_at'    => now(),
            'status'       => 'active',
        ]);

        return back()->with('success', 'Evento asignado correctamente.');
    }

    public function updateEventStatus(Request $request, User $media, Event $event)
    {
        $this->authorizeMedia($media);

        $request->validate([
            'status' => 'required|in:assigned,checked_in,no_show,rejected',
        ]);

        $newStatus = $request->status;
        $previousStatus = DB::table('event_media')
            ->where('media_id', $media->id)
            ->where('event_id', $event->id)
            ->value('status');

        if ($newStatus === 'rejected' && $previousStatus !== 'rejected') {
            EventPass::where('user_id', $media->id)
                ->where('event_id', $event->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);
        }

        if ($previousStatus === 'rejected' && $newStatus !== 'rejected') {
            EventPass::where('user_id', $media->id)
                ->where('event_id', $event->id)
                ->where('status', 'cancelled')
                ->update(['status' => 'active']);

            if ($media->status === 'rejected') {
                $media->update(['status' => 'applicant']);
            }
        }

        DB::table('event_media')
            ->where('media_id', $media->id)
            ->where('event_id', $event->id)
            ->update(['status' => $newStatus]);

        if ($newStatus === 'rejected') {
            $totalEvents = DB::table('event_media')->where('media_id', $media->id)->count();
            $rejectedEvents = DB::table('event_media')->where('media_id', $media->id)->where('status', 'rejected')->count();
            if ($totalEvents > 0 && $totalEvents === $rejectedEvents && $media->status !== 'inactive') {
                $media->update(['status' => 'rejected']);
            }
        }

        return back()->with('success', 'Estado del evento actualizado.');
    }

    public function removeEvent(User $media, Event $event)
    {
        $this->authorizeMedia($media);

        DB::transaction(function () use ($media, $event) {
            EventPass::where('user_id', $media->id)->where('event_id', $event->id)->delete();
            MediaAssistant::where('media_id', $media->id)->where('event_id', $event->id)->delete();
            $media->eventsAsMedia()->detach($event->id);
        });

        return back()->with('success', 'Evento removido.');
    }

    // ──────────────────────────────────────────────
    //  Status
    // ──────────────────────────────────────────────

    public function updateStatus(Request $request, User $media)
    {
        $this->authorizeMedia($media);

        $request->validate([
            'status' => 'required|in:applicant,pending,active,inactive',
        ]);

        $oldStatus = $media->status;
        $newStatus = $request->status;

        if ($newStatus === 'inactive') {
            $eventIds = $media->eventsAsMedia->pluck('id')->toArray();
            DB::table('event_media')->where('media_id', $media->id)->whereIn('event_id', $eventIds)
                ->update(['status' => 'rejected']);
            EventPass::where('user_id', $media->id)->whereIn('event_id', $eventIds)
                ->where('status', 'active')->update(['status' => 'cancelled']);
        }

        if ($oldStatus === 'inactive' && in_array($newStatus, ['pending', 'applicant'])) {
            DB::table('event_media')->where('media_id', $media->id)->where('status', 'rejected')
                ->update(['status' => 'assigned']);
            EventPass::where('user_id', $media->id)->where('status', 'cancelled')
                ->update(['status' => 'active']);
        }

        $media->update(['status' => $newStatus]);

        return back()->with('success', 'Estado actualizado.');
    }

    // ──────────────────────────────────────────────
    //  Assistants
    // ──────────────────────────────────────────────

    public function storeAssistant(Request $request, User $media)
    {
        $this->authorizeMedia($media);

        $request->validate([
            'full_name'   => 'required|string|max:255',
            'document_id' => 'nullable|string|max:255',
            'phone'       => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'event_id'    => 'required|exists:events,id',
        ]);

        $currentCount = MediaAssistant::where('media_id', $media->id)->where('event_id', $request->event_id)->count();
        $maxAllowed = $media->mediaProfile?->max_assistants ?? 0;

        if ($currentCount >= $maxAllowed) {
            return back()->with('error', "Máximo {$maxAllowed} asistentes permitidos para este media.");
        }

        $assistant = MediaAssistant::create([
            'media_id'    => $media->id,
            'event_id'    => $request->event_id,
            'full_name'   => $request->full_name,
            'document_id' => $request->document_id,
            'phone'       => $request->phone,
            'email'       => $request->email,
        ]);

        // Create user for assistant if email provided
        if ($request->filled('email')) {
            $existingUser = User::where('email', $request->email)->first();
            if (!$existingUser) {
                $assistantUser = User::create([
                    'first_name' => explode(' ', $request->full_name)[0],
                    'last_name'  => implode(' ', array_slice(explode(' ', $request->full_name), 1)) ?: '',
                    'email'      => $request->email,
                    'phone'      => $request->phone,
                    'role'       => 'media',
                    'status'     => 'pending',
                    'password'   => Hash::make('runway7'),
                ]);

                // Generate pass for assistant
                EventPass::create([
                    'event_id'     => $request->event_id,
                    'user_id'      => $assistantUser->id,
                    'issued_by'    => auth()->id(),
                    'qr_code'      => EventPass::generateQrCode(),
                    'pass_type'    => 'media',
                    'holder_name'  => $request->full_name,
                    'holder_email' => $request->email,
                    'issued_at'    => now(),
                    'status'       => 'active',
                ]);

                // Send onboarding email to assistant
                $event = Event::find($request->event_id);
                $log = CommunicationLog::create([
                    'user_id' => $assistantUser->id,
                    'sent_by' => auth()->id(),
                    'type'    => 'email',
                    'channel' => 'media_assistant_onboarding',
                    'status'  => 'queued',
                ]);

                SendMediaAssistantOnboardingJob::dispatch(
                    assistantUserId: $assistantUser->id,
                    mediaName: $media->full_name,
                    eventName: $event?->name,
                    logId: $log->id,
                );
            }
        }

        return back()->with('success', 'Asistente agregado.');
    }

    public function destroyAssistant(User $media, MediaAssistant $assistant)
    {
        $this->authorizeMedia($media);
        abort_unless($assistant->media_id === $media->id, 404);
        $assistant->delete();

        return back()->with('success', 'Asistente eliminado.');
    }

    // ──────────────────────────────────────────────
    //  Onboarding Email & SMS
    // ──────────────────────────────────────────────

    public function sendOnboardingEmail(Request $request, User $media)
    {
        $this->authorizeMedia($media);

        $log = CommunicationLog::create([
            'user_id' => $media->id,
            'sent_by' => $request->user()->id,
            'type'    => 'email',
            'channel' => 'media_onboarding',
            'status'  => 'queued',
        ]);

        SendMediaOnboardingJob::dispatch(userId: $media->id, logId: $log->id);
        $media->update(['welcome_email_sent_at' => now()]);

        return back()->with('success', 'Email de onboarding enviado.');
    }

    public function sendOnboardingSms(Request $request, User $media)
    {
        $this->authorizeMedia($media);

        $log = CommunicationLog::create([
            'user_id' => $media->id,
            'sent_by' => $request->user()->id,
            'type'    => 'sms',
            'channel' => 'media_onboarding_sms',
            'status'  => 'queued',
        ]);

        SendMediaOnboardingSmsJob::dispatch(
            userId: $media->id,
            sentBy: $request->user()->id,
            logId: $log->id,
        );

        return back()->with('success', 'SMS de onboarding enviado.');
    }

    public function sendBulkOnboardingEmails(Request $request)
    {
        $pending = User::media()
            ->where('status', 'pending')
            ->whereNull('welcome_email_sent_at')
            ->whereHas('eventsAsMedia')
            ->get();

        if ($pending->isEmpty()) {
            return back()->with('info', 'No hay media pendientes de recibir correo.');
        }

        $count = 0;
        foreach ($pending as $media) {
            $log = CommunicationLog::create([
                'user_id' => $media->id,
                'sent_by' => $request->user()->id,
                'type'    => 'email',
                'channel' => 'media_onboarding',
                'status'  => 'queued',
            ]);

            SendMediaOnboardingJob::dispatch(userId: $media->id, logId: $log->id);
            $media->update(['welcome_email_sent_at' => now()]);
            $count++;
        }

        return back()->with('success', "{$count} emails de onboarding encolados.");
    }

    public function sendBulkOnboardingSms(Request $request)
    {
        $balance = $this->twilioService->getBalance();
        if (!$balance || (float) str_replace(',', '', $balance['balance']) <= 0) {
            return back()->with('error', 'Saldo insuficiente en Twilio.');
        }

        $pending = User::media()
            ->where('status', 'pending')
            ->whereNotNull('phone')
            ->where('phone', 'like', '+%')
            ->whereNull('sms_sent_at')
            ->whereHas('eventsAsMedia')
            ->get();

        if ($pending->isEmpty()) {
            return back()->with('info', 'No hay media pendientes de recibir SMS.');
        }

        $count = 0;
        foreach ($pending as $media) {
            $log = CommunicationLog::create([
                'user_id' => $media->id,
                'sent_by' => $request->user()->id,
                'type'    => 'sms',
                'channel' => 'media_onboarding_sms',
                'status'  => 'queued',
            ]);

            SendMediaOnboardingSmsJob::dispatch(
                userId: $media->id,
                sentBy: $request->user()->id,
                logId: $log->id,
            );
            $count++;
        }

        return back()->with('success', "{$count} SMS de onboarding encolados.");
    }
}
