<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        return match($user->role) {
            'admin'        => $this->adminDashboard(),
            'operation'    => $this->operationDashboard(),
            'accounting'   => redirect()->route('admin.accounting.dashboard'),
            'sales'        => redirect()->route('admin.sales.dashboard'),
            'sponsorship'  => redirect()->route('admin.sponsorship.dashboard'),
            default        => $this->defaultDashboard($user),
        };
    }

    private function adminDashboard(): Response
    {
        $stats = [
            'total_users'        => User::count(),
            'total_internal'     => User::internalTeam()->count(),
            'total_participants' => User::participants()->count(),
            'total_attendees'    => User::attendees()->count(),

            'admin'            => User::role('admin')->count(),
            'accounting'       => User::role('accounting')->count(),
            'operation'        => User::role('operation')->count(),
            'tickets_manager'  => User::role('tickets_manager')->count(),
            'marketing'        => User::role('marketing')->count(),
            'public_relations' => User::role('public_relations')->count(),
            'sales'            => User::role('sales')->count(),

            'designer'  => User::designers()->count(),
            'model'     => User::models()->count(),
            'media'     => User::role('media')->count(),
            'volunteer' => User::role('volunteer')->count(),
            'staff'     => User::role('staff')->count(),

            'attendee'      => User::role('attendee')->count(),
            'vip'           => User::vips()->count(),
            'influencer'    => User::role('influencer')->count(),
            'press'         => User::press()->count(),
            'sponsor'       => User::sponsors()->count(),
            'complementary' => User::role('complementary')->count(),

            'active_events' => Event::where('status', 'active')->count(),
            'total_events'  => Event::count(),
        ];

        return Inertia::render('Admin/Dashboard', compact('stats'));
    }

    private function operationDashboard(): Response
    {
        // ── Eventos ──────────────────────────────────────────────────
        $eventStats = [
            'active'    => Event::where('status', 'active')->count(),
            'published' => Event::where('status', 'published')->count(),
            'draft'     => Event::where('status', 'draft')->count(),
            'completed' => Event::where('status', 'completed')->count(),
            'cancelled' => Event::where('status', 'cancelled')->count(),
            'total'     => Event::count(),
        ];

        // ── Participantes ────────────────────────────────────────────
        $participants = [
            'designers'  => User::designers()->count(),
            'models'     => User::models()->count(),
            'media'      => User::role('media')->count(),
            'volunteers' => User::role('volunteer')->count(),
            'staff'      => User::role('staff')->count(),
        ];

        // ── Distribución estado designers ────────────────────────────
        $designerStatus = [
            'registered' => User::designers()->where('status', 'registered')->count(),
            'pending'    => User::designers()->where('status', 'pending')->count(),
            'active'     => User::designers()->where('status', 'active')->count(),
            'inactive'   => User::designers()->where('status', 'inactive')->count(),
        ];

        // ── Onboarding ───────────────────────────────────────────────
        $totalDesigners   = User::designers()->count();
        $emailSent        = User::designers()->whereNotNull('welcome_email_sent_at')->count();
        $pendingOnboarding = User::designers()->whereIn('status', ['registered', 'pending'])->whereNull('welcome_email_sent_at')->count();

        // ── Materiales ───────────────────────────────────────────────
        $totalMaterials     = \DB::table('designer_materials')->count();
        $completedMaterials = \DB::table('designer_materials')->whereIn('status', ['submitted', 'confirmed'])->count();

        // ── Fittings ─────────────────────────────────────────────────
        $designersWithFitting = \DB::table('fitting_assignments')->distinct('designer_id')->count('designer_id');
        $designersInActiveEvents = \DB::table('event_designer')
            ->join('events', 'events.id', '=', 'event_designer.event_id')
            ->whereIn('events.status', ['active', 'published'])
            ->distinct('event_designer.designer_id')
            ->count('event_designer.designer_id');

        // ── Passes ───────────────────────────────────────────────────
        $totalPasses    = \DB::table('event_passes')->count();
        $checkedInPasses = \DB::table('event_passes')->whereNotNull('checked_in_at')->count();

        // ── Eventos activos (detalle) ─────────────────────────────────
        $activeEvents = Event::whereIn('status', ['active', 'published'])
            ->orderBy('start_date')
            ->get()
            ->map(fn($event) => [
                'id'              => $event->id,
                'name'            => $event->name,
                'status'          => $event->status,
                'start_date'      => $event->start_date?->format('M d, Y'),
                'end_date'        => $event->end_date?->format('M d, Y'),
                'designers_count' => \DB::table('event_designer')->where('event_id', $event->id)->count(),
                'models_count'    => \DB::table('event_model')->where('event_id', $event->id)->count(),
            ]);

        // ── Registros últimos 7 días ──────────────────────────────────
        $recentDesigners = User::designers()->where('created_at', '>=', now()->subDays(7))->count();
        $recentModels    = User::models()->where('created_at', '>=', now()->subDays(7))->count();

        // ── Registros mensuales (últimos 6 meses) ────────────────────
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $monthly[] = [
                'label'     => $m->format('M Y'),
                'designers' => User::designers()->whereYear('created_at', $m->year)->whereMonth('created_at', $m->month)->count(),
                'models'    => User::models()->whereYear('created_at', $m->year)->whereMonth('created_at', $m->month)->count(),
            ];
        }

        return Inertia::render('Admin/OperationDashboard', [
            'eventStats'      => $eventStats,
            'participants'    => $participants,
            'designerStatus'  => $designerStatus,
            'onboarding'      => ['total' => $totalDesigners, 'sent' => $emailSent, 'pending' => $pendingOnboarding],
            'materials'       => ['total' => $totalMaterials, 'completed' => $completedMaterials],
            'fittings'        => ['assigned' => $designersWithFitting, 'total' => $designersInActiveEvents],
            'passes'          => ['total' => $totalPasses, 'checked_in' => $checkedInPasses],
            'activeEvents'    => $activeEvents,
            'recent'          => ['designers' => $recentDesigners, 'models' => $recentModels],
            'monthly'         => $monthly,
        ]);
    }

    private function defaultDashboard(User $user): Response
    {
        $stats = [
            'active_events'  => Event::where('status', 'active')->count(),
            'total_events'   => Event::count(),
            'total_designers' => User::designers()->count(),
            'total_models'    => User::models()->count(),
        ];

        return Inertia::render('Admin/Dashboard', compact('stats'));
    }
}
