<?php

namespace App\Http\Controllers\Admin\Sponsorship;

use App\Http\Controllers\Controller;
use App\Models\Sponsorship\Lead;
use App\Models\Sponsorship\Package;
use App\Models\Sponsorship\Registration;
use App\Models\Sponsorship\RegistrationDocument;
use App\Models\SponsorProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ConversionController extends Controller
{
    private function authorizeLead(Lead $lead): void
    {
        $u = auth()->user();
        $isLider = $u->role === 'admin' || ($u->role === 'sponsorship' && $u->sponsorship_type === 'lider');
        if (!$isLider && $lead->assigned_to_user_id !== $u->id) {
            abort(403, 'Este lead no está asignado a ti.');
        }
    }

    public function show(Lead $lead)
    {
        $this->authorizeLead($lead);

        if ($lead->converted_user_id) {
            return redirect()->route('admin.sponsorship.leads.show', $lead)
                ->with('error', 'Este lead ya fue convertido a sponsor.');
        }

        $lead->load(['company', 'events:id,name,start_date', 'primaryEmail', 'emails']);

        return Inertia::render('Admin/Sponsorship/Leads/Convert', [
            'lead'     => $lead,
            'packages' => Package::where('is_active', true)->orderBy('name')->get(['id', 'name', 'price', 'assistants_count']),
        ]);
    }

    public function store(Request $request, Lead $lead)
    {
        $this->authorizeLead($lead);

        if ($lead->converted_user_id) {
            return back()->with('error', 'Este lead ya fue convertido.');
        }

        $validated = $request->validate([
            // Company
            'company_name'     => 'required|string|max:255',
            'company_website'  => 'nullable|string|max:500',
            'company_instagram'=> 'nullable|string|max:150',
            'company_industry' => 'nullable|string|max:150',
            'company_country'  => 'nullable|string|max:100',
            'company_notes'    => 'nullable|string',
            'company_logo'     => 'nullable|string|max:500',

            // Email confirmation
            'email'            => 'required|email|max:255',
            'email_confirmed'  => 'accepted',

            // Registration
            'event_id'         => 'required|exists:events,id',
            'package_id'       => 'required|exists:sponsorship_packages,id',
            'agreed_price'     => 'required|numeric|min:0',
            'downpayment'      => 'required|numeric|min:0',
            'installments_count' => 'required|integer|min:1|max:60',
            'notes'            => 'nullable|string',

            // Documents
            'documents'        => 'nullable|array',
            'documents.*'      => 'file|max:20480',
        ], [
            'email_confirmed.accepted' => 'You must confirm the primary email before converting.',
        ]);

        // Validar que el email no exista en users
        $emailLower = mb_strtolower(trim($validated['email']));
        $existingUser = User::withTrashed()->whereRaw('LOWER(email) = ?', [$emailLower])->first();
        if ($existingUser) {
            return back()->withErrors([
                'email' => "Ya existe un usuario con ese email (rol: {$existingUser->role}). Usa otro email o contacta al admin.",
            ])->withInput();
        }

        $lead->load('company');

        $result = DB::transaction(function () use ($validated, $lead, $emailLower, $request) {
            // 1. Actualizar datos de la Company
            $company = $lead->company;
            $company->update([
                'name'      => $validated['company_name'],
                'website'   => $validated['company_website'] ?? $company->website,
                'instagram' => $validated['company_instagram'] ?? $company->instagram,
                'industry'  => $validated['company_industry'] ?? $company->industry,
                'country'   => $validated['company_country'] ?? $company->country,
                'notes'     => $validated['company_notes'] ?? $company->notes,
                'logo'      => $validated['company_logo'] ?? $company->logo,
            ]);

            // 2. Crear user sponsor
            $user = User::create([
                'first_name' => $lead->first_name,
                'last_name'  => $lead->last_name,
                'email'      => $emailLower,
                'phone'      => $lead->phone,
                'password'   => bcrypt('runway7'),
                'role'       => 'sponsor',
                'status'     => 'registered',
            ]);

            // 3. Crear sponsor_profile
            SponsorProfile::create([
                'user_id'      => $user->id,
                'company_name' => $company->name,
                'website'      => $company->website,
                'logo'         => $company->logo,
                'notes'        => $company->notes,
            ]);

            // 4. Crear registration
            $registration = Registration::create([
                'lead_id'             => $lead->id,
                'sponsor_user_id'     => $user->id,
                'company_id'          => $company->id,
                'event_id'            => $validated['event_id'],
                'package_id'          => $validated['package_id'],
                'agreed_price'        => $validated['agreed_price'],
                'downpayment'         => $validated['downpayment'],
                'installments_count' => $validated['installments_count'],
                'notes'               => $validated['notes'] ?? null,
                'status'              => 'registered',
                'created_by_user_id'  => auth()->id(),
            ]);

            // 5. Documentos
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $path = $file->store("sponsorship/registrations/{$registration->id}", 'public');
                    RegistrationDocument::create([
                        'registration_id'     => $registration->id,
                        'uploaded_by_user_id' => auth()->id(),
                        'type'                => 'contract',
                        'original_name'       => $file->getClientOriginalName(),
                        'path'                => $path,
                        'mime_type'           => $file->getMimeType(),
                        'size'                => $file->getSize(),
                    ]);
                }
            }

            // 6. Marcar este lead como winner
            $lead->update([
                'converted_user_id'   => $user->id,
                'is_contract_winner'  => true,
                'status'              => 'cerrado',
            ]);

            // 7. Cascada: otros leads de la company con status activo → cerrado
            Lead::where('company_id', $company->id)
                ->where('id', '!=', $lead->id)
                ->whereNotIn('status', ['cerrado', 'rechazado', 'perdido'])
                ->update(['status' => 'cerrado']);

            return [
                'user'         => $user,
                'registration' => $registration,
            ];
        });

        return redirect()->route('admin.sponsorship.leads.show', $lead)
            ->with('success', "Lead convertido a sponsor correctamente. Contraseña temporal: runway7 — recuerda enviar el onboarding desde la página de sponsors.");
    }
}
