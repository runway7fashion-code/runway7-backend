<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\Sponsorship\SendLeadConfirmationEmailJob;
use App\Models\Sponsorship\Company;
use App\Models\Sponsorship\Lead;
use App\Models\Sponsorship\LeadActivity;
use App\Models\Sponsorship\LeadEmail;
use App\Models\User;
use App\Notifications\Sponsorship\NewSponsorshipLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SponsorshipLeadRegistrationController extends Controller
{
    public function register(Request $request)
    {
        // Honeypot: silent success, no record created
        if ($request->filled('hp_website')) {
            return response()->json(['message' => 'Application received.'], 201);
        }

        $validated = $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'email'         => 'required|email|max:255',
            'company_name'  => 'required|string|max:255',
            'website_url'   => 'nullable|url|max:500',
            'instagram'     => 'nullable|string|max:150',
            'notes'         => 'nullable|string|max:5000',
            'terms'         => 'accepted',
        ], [
            'terms.accepted' => 'You must agree to the terms and conditions.',
        ]);

        // Sanitize Instagram
        if (!empty($validated['instagram'])) {
            $ig = explode('?', $validated['instagram'])[0];
            $ig = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $ig);
            $ig = trim($ig, '/');
            $ig = ltrim($ig, '@');
            $validated['instagram'] = $ig ?: null;
        }

        $email = mb_strtolower(trim($validated['email']));

        // Deduplicación silenciosa: si el email ya está registrado, responder success sin revelar
        $existing = LeadEmail::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing) {
            return response()->json([
                'message' => 'Thank you for your interest! Our Sponsorship team will contact you soon.',
            ], 201);
        }

        $lead = DB::transaction(function () use ($validated, $email) {
            // Buscar o crear company por nombre case-insensitive
            $companyName = trim($validated['company_name']);
            $company = Company::whereRaw('LOWER(name) = ?', [mb_strtolower($companyName)])->first();
            if (!$company) {
                $company = Company::create([
                    'name'               => $companyName,
                    'website'            => $validated['website_url'] ?? null,
                    'instagram'          => $validated['instagram'] ?? null,
                    'created_by_user_id' => null,
                ]);
            }

            $lead = Lead::create([
                'company_id'            => $company->id,
                'first_name'            => $validated['first_name'],
                'last_name'             => $validated['last_name'],
                'website_url'           => $validated['website_url'] ?? null,
                'instagram'             => $validated['instagram'] ?? null,
                'status'                => 'nuevo',
                'source'                => 'website',
                'registered_by_user_id' => null,
                'assigned_to_user_id'   => null, // Only a líder can assign
                'notes'                 => $validated['notes'] ?? null,
            ]);

            LeadEmail::create([
                'lead_id'    => $lead->id,
                'email'      => $email,
                'is_primary' => true,
            ]);

            LeadActivity::create([
                'lead_id'             => $lead->id,
                'created_by_user_id'  => null,
                'assigned_to_user_id' => null,
                'type'                => 'system',
                'title'               => 'Lead registered from the website',
                'description'         => "Email: {$email}, Company: {$company->name}",
                'status'              => 'completed',
                'completed_at'        => now(),
            ]);

            return $lead;
        });

        // Enviar email de confirmación via job (con reintentos)
        try {
            SendLeadConfirmationEmailJob::dispatch($lead->id);
        } catch (\Throwable $e) {
            Log::warning('Sponsorship lead confirmation job dispatch failed: ' . $e->getMessage());
        }

        // Notificar a admins + líderes sponsorship (sin duplicar)
        try {
            $notifiables = User::where(function ($q) {
                    $q->where('role', 'admin')
                      ->orWhere(function ($q2) {
                          $q2->where('role', 'sponsorship')->where('sponsorship_type', 'lider');
                      });
                })
                ->get();

            foreach ($notifiables as $u) {
                $u->notify(new NewSponsorshipLead($lead));
            }
        } catch (\Throwable $e) {
            Log::warning('Sponsorship lead notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Thank you for your interest! Our Sponsorship team will contact you soon.',
        ], 201);
    }
}
