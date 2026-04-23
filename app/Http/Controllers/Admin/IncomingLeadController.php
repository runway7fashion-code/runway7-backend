<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\DesignerLead;
use App\Models\Event;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class IncomingLeadController extends Controller
{
    public function index(Request $request)
    {
        $query = DesignerLead::where('status', 'redirected')
            ->with(['redirectedByUser:id,first_name,last_name', 'convertedUser:id,first_name,last_name']);

        if ($request->filled('redirect_type')) {
            $query->where('redirect_type', $request->redirect_type);
        }

        if ($request->filled('redirect_status')) {
            $query->where('redirect_status', $request->redirect_status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'ilike', "%{$s}%")
                  ->orWhere('last_name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
                  ->orWhere('phone', 'ilike', "%{$s}%");
            });
        }

        $stats = [
            'total'     => DesignerLead::where('status', 'redirected')->count(),
            'new'       => DesignerLead::where('status', 'redirected')->where('redirect_status', 'new')->count(),
            'converted' => DesignerLead::where('status', 'redirected')->where('redirect_status', 'converted')->count(),
            'rejected'  => DesignerLead::where('status', 'redirected')->where('redirect_status', 'rejected')->count(),
        ];

        $leads = $query->orderByRaw("CASE WHEN redirect_status = 'new' THEN 0 ELSE 1 END")
            ->orderBy('redirected_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Operations/IncomingLeads', [
            'leads'   => $leads,
            'stats'   => $stats,
            'events'  => Event::whereNull('deleted_at')->where('status', '!=', 'cancelled')->select('id', 'name')->orderBy('start_date', 'desc')->get(),
            'filters' => $request->only(['search', 'redirect_type', 'redirect_status']),
        ]);
    }

    public function convert(Request $request, DesignerLead $lead)
    {
        $request->validate([
            'role'     => 'required|in:model,media,volunteer',
            'event_id' => 'nullable|exists:events,id',
        ]);

        if ($lead->redirect_status === 'converted') {
            return back()->with('error', 'This lead has already been converted.');
        }

        $role = $request->role;

        // Check email uniqueness
        if (User::where('email', $lead->email)->exists()) {
            return back()->withErrors(['email' => "A user with email {$lead->email} already exists."]);
        }

        $user = DB::transaction(function () use ($lead, $role, $request) {
            $user = User::create([
                'first_name' => $lead->first_name,
                'last_name'  => $lead->last_name,
                'email'      => $lead->email,
                'phone'      => $lead->phone,
                'role'       => $role,
                'status'     => 'applicant',
                'password'   => Hash::make('runway7'),
            ]);

            // Create role-specific profile
            if ($role === 'model') {
                $user->modelProfile()->create([
                    'instagram' => $lead->instagram,
                    'location'  => $lead->country,
                ]);
            } elseif ($role === 'volunteer') {
                $user->volunteerProfile()->create([
                    'instagram' => $lead->instagram,
                    'location'  => $lead->country,
                ]);
            } elseif ($role === 'media') {
                $user->mediaProfile()->create([
                    'instagram' => $lead->instagram,
                    'location'  => $lead->country,
                ]);
            }

            // Assign to event if provided
            if ($request->event_id) {
                DB::table("event_{$role}")->insert([
                    "{$role}_id" => $user->id,
                    'event_id'   => $request->event_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update lead
            $lead->update([
                'redirect_status'  => 'converted',
                'converted_user_id' => $user->id,
            ]);

            LeadActivity::create([
                'lead_id'      => $lead->id,
                'user_id'      => auth()->id(),
                'type'         => 'system',
                'title'        => "Converted to {$role} by Operations",
                'description'  => "User ID: {$user->id} — {$user->email}",
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            return $user;
        });

        return back()->with('success', "{$lead->first_name} {$lead->last_name} converted to {$role} successfully.");
    }

    public function reject(Request $request, DesignerLead $lead)
    {
        $request->validate([
            'redirect_note' => 'nullable|string|max:500',
        ]);

        $lead->update([
            'redirect_status' => 'rejected',
            'redirect_note'   => $request->redirect_note ?: $lead->redirect_note,
        ]);

        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => auth()->id(),
            'type'         => 'system',
            'title'        => 'Rejected by Operations — does not apply',
            'description'  => $request->redirect_note,
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Lead marked as rejected.');
    }
}
