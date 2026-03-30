<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\LeadConfirmationMail;
use App\Models\DesignerLead;
use App\Models\Event;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Notifications\NewDesignerLead;

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
        // Honeypot check (hidden field name="hp_website" in the form)
        if ($request->filled('hp_website')) {
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

        $eventId = $validated['event_id'];
        unset($validated['event_id']);

        // Check if lead with same email already exists
        $existingLead = DesignerLead::where('email', $validated['email'])->first();

        if ($existingLead) {
            // Check if already registered for this event
            if ($existingLead->events()->where('events.id', $eventId)->exists()) {
                return response()->json([
                    'message' => 'You have already submitted an application for this event. Our team will contact you soon. If you need immediate assistance, please email us at designers@runway7fashion.com or reach us via WhatsApp at https://wa.me/message/4M6DBP2NATXFC1',
                ], 422);
            }

            // Add new event to existing lead
            $existingLead->events()->attach($eventId);

            // Update fields if changed
            $existingLead->update(array_filter([
                'phone'          => $validated['phone'] ?? null,
                'company_name'   => $validated['company_name'] ?? null,
                'retail_category'=> $validated['retail_category'] ?? null,
                'website_url'    => $validated['website_url'] ?? null,
                'instagram'      => $validated['instagram'] ?? null,
            ]));

            // Reactivate lost leads (client stays client)
            if ($existingLead->status === 'lost') {
                $existingLead->update(['status' => 'qualified']);
            }

            LeadActivity::create([
                'lead_id'      => $existingLead->id,
                'user_id'      => null,
                'type'         => 'system',
                'title'        => 'Nuevo evento agregado desde la web: ' . \App\Models\Event::find($eventId)?->name,
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            $lead = $existingLead;
        } else {
            $lead = DesignerLead::create(array_merge($validated, [
                'status' => 'new',
                'source' => 'website_designers',
            ]));

            // Link event
            $lead->events()->attach($eventId);

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
        }

        // Send confirmation email to the lead
        try {
            $lead->load('events');
            Mail::to($lead->email, "{$lead->first_name} {$lead->last_name}")
                ->send(new LeadConfirmationMail($lead));
        } catch (\Exception $e) {
            \Log::warning('Lead confirmation email failed: ' . $e->getMessage());
        }

        // Notify leader(s) only — no assignment yet, leader qualifies first
        try {
            $leaders = User::where('role', 'sales')
                ->where('sales_type', 'lider')
                ->whereNull('deleted_at')
                ->get();

            foreach ($leaders as $leader) {
                $leader->notify(new NewDesignerLead($lead));
            }
        } catch (\Exception $e) {
            \Log::warning('Lead notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Thank you for your interest! Our team will contact you soon.',
        ], 201);
    }
}
