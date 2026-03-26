<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DesignerLead;
use App\Models\Event;
use App\Models\LeadActivity;
use App\Models\User;
use App\Notifications\NewDesignerLead;
use App\Services\LeadAssignmentService;
use Illuminate\Http\Request;

class LeadRegistrationController extends Controller
{
    public function events()
    {
        $events = Event::where('status', 'active')
            ->whereNull('deleted_at')
            ->select('id', 'name')
            ->orderBy('start_date')
            ->get();

        return response()->json($events);
    }

    public function register(Request $request)
    {
        // Honeypot check
        if ($request->filled('website_url')) {
            return response()->json(['message' => 'Application received.'], 201);
        }

        $validated = $request->validate([
            'first_name'             => 'required|string|max:100',
            'last_name'              => 'required|string|max:100',
            'email'                  => 'required|email|max:255',
            'phone'                  => 'required|string|max:30',
            'country'                => 'required|string|max:100',
            'company_name'           => 'required|string|max:255',
            'retail_category'        => 'required|string|max:100',
            'website_url'            => 'nullable|url|max:500',
            'instagram'              => 'nullable|string|max:100',
            'designs_ready'          => 'required|string|max:50',
            'budget'                 => 'required|string|max:100',
            'past_shows'             => 'required|string|max:10',
            'event_id'               => 'required|exists:events,id',
            'preferred_contact_time' => 'nullable|string|max:20',
        ]);

        // Sanitize Instagram
        if (!empty($validated['instagram'])) {
            $ig = $validated['instagram'];
            $ig = explode('?', $ig)[0];
            $ig = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $ig);
            $ig = trim($ig, '/');
            $ig = ltrim($ig, '@');
            $validated['instagram'] = $ig;
        }

        // Check if lead with same email already exists for this event
        $existingLead = DesignerLead::where('email', $validated['email'])
            ->where('event_id', $validated['event_id'])
            ->first();

        if ($existingLead) {
            return response()->json([
                'message' => 'You have already submitted an application for this event. Our team will contact you soon.',
            ], 422);
        }

        $lead = DesignerLead::create(array_merge($validated, [
            'status' => 'new',
            'source' => 'website',
        ]));

        // Log creation activity
        LeadActivity::create([
            'lead_id'      => $lead->id,
            'user_id'      => null,
            'type'         => 'system',
            'title'        => 'Lead registrado desde la web',
            'description'  => "Email: {$lead->email}, Empresa: {$lead->company_name}",
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // Round-robin assignment
        $assignmentService = new LeadAssignmentService();
        $assignedTo = $assignmentService->assignRoundRobin($lead);

        // Schedule initial call if preferred time provided
        if ($assignedTo) {
            $lead->refresh();
            $assignmentService->scheduleInitialCall($lead);
        }

        // Notify leader(s) and assigned advisor
        $leaders = User::where('role', 'sales')
            ->where('sales_type', 'lider')
            ->whereNull('deleted_at')
            ->get();

        foreach ($leaders as $leader) {
            $leader->notify(new NewDesignerLead($lead));
        }

        if ($assignedTo) {
            $assignedTo->notify(new NewDesignerLead($lead));
        }

        return response()->json([
            'message' => 'Thank you for your interest! Our team will contact you soon.',
        ], 201);
    }
}
