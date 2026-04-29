<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\LeadConfirmationMail;
use App\Models\Country;
use App\Models\DesignerCategory;
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

    public function categories()
    {
        return response()->json(
            DesignerCategory::where('is_active', true)->ordered()->pluck('name')
        );
    }

    public function countries()
    {
        return response()->json(
            Country::active()->ordered()->get(['name', 'code', 'phone', 'flag'])
        );
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
            'event_ids'              => 'required|array|min:1',
            'event_ids.*'            => 'exists:events,id',
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

        $eventIds = $validated['event_ids'];
        unset($validated['event_ids']);

        // Check if lead with same email already exists
        $existingLead = DesignerLead::where('email', $validated['email'])->first();

        if ($existingLead) {
            // Attach only new events
            $existingEventIds = $existingLead->events()->pluck('events.id')->toArray();
            $newEventIds = array_diff($eventIds, $existingEventIds);

            if (empty($newEventIds)) {
                return response()->json([
                    'message' => 'You have already submitted an application for the selected event(s). Our team will contact you soon. If you need immediate assistance, please email us at designers@runway7fashion.com or reach us via WhatsApp at https://wa.me/message/4M6DBP2NATXFC1',
                ], 422);
            }

            foreach ($newEventIds as $eid) {
                $existingLead->events()->attach($eid);
            }

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

            $eventNames = \App\Models\Event::whereIn('id', $newEventIds)->pluck('name')->join(', ');
            LeadActivity::create([
                'lead_id'      => $existingLead->id,
                'user_id'      => null,
                'type'         => 'system',
                'title'        => 'Nuevo(s) evento(s) agregado(s) desde la web: ' . $eventNames,
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            $lead = $existingLead;
        } else {
            $lead = DesignerLead::create(array_merge($validated, [
                'status' => 'new',
                'source' => 'website_designers',
            ]));

            // Link events
            foreach ($eventIds as $eid) {
                $lead->events()->attach($eid);
            }

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
            $leaders = User::where(function ($q) {
                    $q->where(fn($qq) => $qq->where('role', 'sales')->where('sales_type', 'lider'))
                      ->orWhereJsonContains('extra_areas', 'sales');
                })->get();

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
