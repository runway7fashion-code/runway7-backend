<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Jobs\Sponsorship\SendSponsorOnboardingEmailJob;
use App\Models\ComplementaryGuest;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\Show;
use App\Models\Sponsorship\Registration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SponsorController extends Controller
{
    private function isLider(): bool
    {
        return auth()->user()?->isLeaderOf('sponsorship') ?? false;
    }

    public function index(Request $request)
    {
        $query = User::where('role', 'sponsor')
            ->with(['sponsorProfile']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'ilike', "%{$s}%")
                  ->orWhere('last_name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
                  ->orWhereHas('sponsorProfile', fn($sp) => $sp->where('company_name', 'ilike', "%{$s}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('onboarding')) {
            if ($request->onboarding === 'sent')     $query->whereNotNull('welcome_email_sent_at');
            if ($request->onboarding === 'not_sent') $query->whereNull('welcome_email_sent_at');
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $sponsors = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // Counts de registrations por sponsor
        $registrationCounts = Registration::selectRaw('sponsor_user_id, COUNT(*) as c')
            ->groupBy('sponsor_user_id')
            ->pluck('c', 'sponsor_user_id')
            ->toArray();

        $sponsors->getCollection()->transform(function ($u) use ($registrationCounts) {
            $u->registrations_count = $registrationCounts[$u->id] ?? 0;
            return $u;
        });

        return Inertia::render('Admin/Sponsorship/Sponsors/Index', [
            'sponsors'   => $sponsors,
            'totalCount' => User::where('role', 'sponsor')->count(),
            'filters'    => $request->only(['search', 'status', 'onboarding', 'date_from', 'date_to']),
        ]);
    }

    public function show(User $user)
    {
        abort_unless($user->role === 'sponsor', 404);

        $user->load(['sponsorProfile']);

        $registrations = Registration::with(['event:id,name,start_date', 'package:id,name', 'company:id,name', 'documents.uploader:id,first_name,last_name', 'creator:id,first_name,last_name'])
            ->where('sponsor_user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalAllowedGuests = $registrations->sum(fn($r) => $r->package?->assistants_count ?? 0);

        $guests = ComplementaryGuest::with(['guest:id,first_name,last_name,email,phone', 'event:id,name', 'eventDay:id,event_id,date,label', 'show:id,name,event_day_id'])
            ->where('host_user_id', $user->id)
            ->where('type', 'sponsor_guest')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Sponsorship/Sponsors/Show', [
            'sponsor'            => $user,
            'registrations'      => $registrations,
            'guests'             => $guests,
            'totalAllowedGuests' => $totalAllowedGuests,
            'events'             => Event::whereNull('deleted_at')->orderBy('start_date', 'desc')->get(['id', 'name']),
            'eventDays'          => EventDay::with('event:id,name')->orderBy('date')->get(['id', 'event_id', 'date', 'label']),
            'shows'              => Show::orderBy('name')->get(['id', 'name', 'event_day_id']),
            'isLider'            => $this->isLider(),
        ]);
    }

    public function sendOnboarding(User $user, Request $request)
    {
        abort_unless($user->role === 'sponsor', 404);

        SendSponsorOnboardingEmailJob::dispatch($user->id, $request->input('registration_id'));

        return back()->with('success', 'Onboarding email queued.');
    }

    public function addGuest(Request $request, User $user)
    {
        abort_unless($user->role === 'sponsor', 404);

        $validated = $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'email'        => 'required|email|max:255',
            'phone'        => 'nullable|string|max:30',
            'event_id'     => 'nullable|exists:events,id',
            'event_day_id' => 'nullable|exists:event_days,id',
            'show_id'      => 'nullable|exists:shows,id',
            'notes'        => 'nullable|string|max:500',
        ]);

        // Validar quota: total de guests del sponsor < sum(package.assistants_count)
        $maxGuests = Registration::with('package:id,assistants_count')
            ->where('sponsor_user_id', $user->id)
            ->get()
            ->sum(fn($r) => $r->package?->assistants_count ?? 0);

        $currentGuests = ComplementaryGuest::where('host_user_id', $user->id)
            ->where('type', 'sponsor_guest')
            ->count();

        if ($currentGuests >= $maxGuests) {
            return back()->withErrors(['quota' => "This sponsor has reached the guest limit of {$maxGuests} allowed by their packages."])->withInput();
        }

        // Validar email único en users
        $emailLower = mb_strtolower(trim($validated['email']));
        if (User::whereRaw('LOWER(email) = ?', [$emailLower])->exists()) {
            return back()->withErrors(['email' => 'A user with that email already exists.'])->withInput();
        }

        DB::transaction(function () use ($validated, $user, $emailLower) {
            $guest = User::create([
                'first_name'         => $validated['first_name'],
                'last_name'          => $validated['last_name'],
                'email'              => $emailLower,
                'phone'              => $validated['phone'] ?? null,
                'password'           => bcrypt('runway7'),
                'role'               => 'complementary',
                'complementary_type' => 'sponsor_guest',
                'status'             => 'active',
            ]);

            ComplementaryGuest::create([
                'guest_user_id' => $guest->id,
                'host_user_id'  => $user->id,
                'type'          => 'sponsor_guest',
                'event_id'      => $validated['event_id'] ?? null,
                'event_day_id'  => $validated['event_day_id'] ?? null,
                'show_id'       => $validated['show_id'] ?? null,
                'notes'         => $validated['notes'] ?? null,
            ]);
        });

        return back()->with('success', 'Guest added. Temporary password: runway7.');
    }

    public function removeGuest(ComplementaryGuest $guest)
    {
        // Eliminar user también (soft delete via relation)
        $guestUserId = $guest->guest_user_id;
        $guest->delete();
        User::where('id', $guestUserId)->delete();

        return back()->with('success', 'Guest removed.');
    }
}
