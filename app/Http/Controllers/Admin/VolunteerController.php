<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VolunteersExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class VolunteerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::where('role', 'volunteer')
            ->with(['volunteerProfile', 'eventsAsStaff']);

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

        return Inertia::render('Admin/Volunteers/Index', [
            'volunteers' => $volunteers,
            'filters' => $request->only(['search', 'status', 'event_id']),
            'events' => $events,
        ]);
    }

    public function create(): Response
    {
        $events = Event::where('status', 'active')
            ->orderBy('start_date')
            ->get(['id', 'name']);

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
            'phone'                  => 'required|string',
            'age'                    => 'required|integer|min:18|max:80',
            'gender'                 => 'required|in:female,male,non_binary',
            'location'               => 'required|string|max:255',
            'instagram'              => 'nullable|string|max:255',
            'tshirt_size'            => 'required|in:XS,S,M,L,XL,XXL',
            'experience'             => 'required|in:none,some,experienced',
            'comfortable_fast_paced' => 'required|in:multitask,structured',
            'full_availability'      => 'required|in:yes,no,partially',
            'contribution'           => 'nullable|string|max:1000',
            'resume_link'            => 'nullable|url|max:500',
            'event_id'               => 'nullable|exists:events,id',
        ]);

        // Sanitizar Instagram
        if (!empty($validated['instagram'])) {
            $ig = $validated['instagram'];
            $ig = strtok($ig, '?');
            $ig = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $ig);
            $ig = rtrim($ig, '/');
            $ig = ltrim($ig, '@');
            $validated['instagram'] = $ig;
        }

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'phone'      => $validated['phone'],
            'role'       => 'volunteer',
            'status'     => 'applicant',
            'password'   => Hash::make(Str::random(16)),
        ]);

        $user->volunteerProfile()->create([
            'age'                    => $validated['age'],
            'gender'                 => $validated['gender'],
            'tshirt_size'            => $validated['tshirt_size'],
            'experience'             => $validated['experience'],
            'comfortable_fast_paced' => $validated['comfortable_fast_paced'],
            'full_availability'      => $validated['full_availability'],
            'contribution'           => $validated['contribution'] ?? null,
            'resume_link'            => $validated['resume_link'] ?? null,
            'instagram'              => $validated['instagram'] ?? null,
            'location'               => $validated['location'],
        ]);

        if (!empty($validated['event_id'])) {
            $user->eventsAsStaff()->attach($validated['event_id'], [
                'assigned_role' => 'volunteer',
                'status'        => 'assigned',
            ]);
        }

        return redirect()->route('admin.volunteers.index')
            ->with('success', 'Voluntario creado correctamente.');
    }

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

    public function destroy(User $volunteer)
    {
        if ($volunteer->role !== 'volunteer') {
            abort(403);
        }

        $volunteer->volunteerProfile()?->delete();
        $volunteer->eventsAsStaff()->detach();
        $volunteer->delete();

        return redirect()->route('admin.volunteers.index')
            ->with('success', 'Voluntario eliminado correctamente.');
    }
}
