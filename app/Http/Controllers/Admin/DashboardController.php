<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
            // Overview
            'total_users'   => User::count(),
            'total_internal'    => User::internalTeam()->count(),
            'total_participants' => User::participants()->count(),
            'total_attendees'   => User::attendees()->count(),

            // Internal team
            'admin'              => User::role('admin')->count(),
            'accounting'         => User::role('accounting')->count(),
            'operation'          => User::role('operation')->count(),
            'tickets_manager'    => User::role('tickets_manager')->count(),
            'marketing'          => User::role('marketing')->count(),
            'public_relations'   => User::role('public_relations')->count(),

            // Participants
            'designer'  => User::designers()->count(),
            'model'     => User::models()->count(),
            'media'     => User::role('media')->count(),
            'volunteer' => User::role('volunteer')->count(),
            'staff'     => User::role('staff')->count(),

            // Attendees
            'attendee'      => User::role('attendee')->count(),
            'vip'           => User::vips()->count(),
            'influencer'    => User::role('influencer')->count(),
            'press'         => User::press()->count(),
            'sponsor'       => User::sponsors()->count(),
            'complementary' => User::role('complementary')->count(),

            // Events
            'active_events' => Event::where('status', 'active')->count(),
            'total_events'  => Event::count(),
        ];

        return Inertia::render('Admin/Dashboard', compact('stats'));
    }
}
