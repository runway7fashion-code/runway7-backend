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
            'admin', 'operation' => $this->adminDashboard(),
            'accounting' => redirect()->route('admin.accounting.dashboard'),
            'sales' => $this->salesDashboard($user),
            default => $this->defaultDashboard($user),
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

    private function salesDashboard(User $user): Response
    {
        $stats = [
            'my_designers' => User::designers()
                ->whereHas('designerProfile', fn($q) => $q->where('sales_rep_id', $user->id))
                ->count(),
            'total_designers' => User::designers()->count(),
            'active_events'   => Event::where('status', 'active')->count(),
        ];

        return Inertia::render('Admin/Dashboard', compact('stats'));
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
