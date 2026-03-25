<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Jobs\SendVolunteerRegistrationEmailJob;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\User;
use App\Notifications\NewVolunteerRegistered;
use App\Services\ActivityLogService;
use App\Support\InstagramSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VolunteerRegistrationController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLog,
    ) {}

    /**
     * Listar eventos publicados (para el dropdown del formulario de WordPress).
     */
    public function events(): JsonResponse
    {
        $events = Event::where('status', 'active')
            ->orderBy('start_date')
            ->get(['id', 'name', 'city', 'start_date', 'end_date']);

        return response()->json($events);
    }

    /**
     * Registrar un voluntario desde el formulario público de WordPress.
     */
    public function store(Request $request): JsonResponse
    {
        // Honeypot
        if ($request->filled('website_url')) {
            return response()->json([
                'message' => 'Your application has been received successfully!',
            ], 201);
        }

        // Sanitizar Instagram
        $request->merge([
            'instagram' => InstagramSanitizer::sanitize($request->input('instagram')),
        ]);

        // Verificar si ya existe (incluir soft-deleted)
        $existingUser = User::withTrashed()->where('email', $request->input('email'))->first();

        // Si está soft-deleted, restaurar
        if ($existingUser && $existingUser->trashed()) {
            $existingUser->restore();
        }

        $validated = $request->validate([
            'first_name'              => 'required|string|max:255',
            'last_name'               => 'required|string|max:255',
            'email'                   => 'required|email',
            'phone'                   => 'required|string',
            'age'                     => 'required|integer|min:18|max:80',
            'gender'                  => 'required|in:female,male,non_binary',
            'location'                => 'required|string|max:255',
            'instagram'               => 'required|string|max:255',
            'tshirt_size'             => 'required|in:XS,S,M,L,XL,XXL',
            'experience'              => 'required|in:none,some,experienced',
            'comfortable_fast_paced'  => 'required|in:multitask,structured',
            'full_availability'       => 'required|in:yes,no,partially',
            'contribution'            => 'required|string|max:1000',
            'resume_link'             => 'required|url|max:500',
            'event_id'                => 'required|exists:events,id',
        ], [
            'first_name.required'             => 'First name is required.',
            'last_name.required'              => 'Last name is required.',
            'email.required'                  => 'Email is required.',
            'email.email'                     => 'Please enter a valid email.',
            'phone.required'                  => 'Phone is required.',
            'age.required'                    => 'Age is required.',
            'age.min'                         => 'You must be at least 18 years old.',
            'gender.required'                 => 'Gender is required.',
            'location.required'               => 'Location is required.',
            'tshirt_size.required'            => 'T-Shirt size is required.',
            'experience.required'             => 'Experience level is required.',
            'comfortable_fast_paced.required' => 'This field is required.',
            'full_availability.required'      => 'This field is required.',
            'contribution.required'           => 'Please tell us how you would contribute.',
            'event_id.required'               => 'Please select an event.',
            'event_id.exists'                 => 'The selected event is not valid.',
            'instagram.required'              => 'Instagram username is required.',
            'resume_link.required'            => 'Resume link is required.',
            'resume_link.url'                 => 'Please enter a valid URL for your resume.',
        ]);

        $eventId = (int) $validated['event_id'];

        // Bloquear si el usuario está inactivo (bloqueado por admin de todo registro)
        if ($existingUser && $existingUser->status === 'inactive') {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact us for assistance.',
                'errors' => ['email' => ['Your account has been deactivated. Please contact us at events@runway7fashion.com']],
            ], 422);
        }

        // Rechazar si el email ya existe con otro rol
        if ($existingUser && $existingUser->role !== 'volunteer') {
            return response()->json([
                'message' => 'This email is already registered with a different role.',
                'errors' => ['email' => ['This email is already registered as ' . $existingUser->role . '. Please use a different email or contact us at operations@runway7fashion.com']],
            ], 422);
        }

        // Verificar si ya está asignado a este evento
        if ($existingUser) {
            $alreadyInEvent = DB::table('event_volunteer')
                ->where('volunteer_id', $existingUser->id)
                ->where('event_id', $eventId)
                ->exists();

            if ($alreadyInEvent) {
                return response()->json([
                    'message' => 'You are already registered for this event.',
                    'errors' => ['email' => ['You are already registered for this event. Please select a different event or contact us at events@runway7fashion.com']],
                ], 422);
            }
        }

        try {
            $isReRegistration = (bool) $existingUser;

            $user = DB::transaction(function () use ($validated, $existingUser, $eventId) {
                if ($existingUser) {
                    // Re-registro: actualizar datos y asignar al nuevo evento
                    $existingUser->update([
                        'first_name' => $validated['first_name'],
                        'last_name'  => $validated['last_name'],
                        'phone'      => $validated['phone'],
                    ]);

                    $profileData = [
                        'age'                    => $validated['age'],
                        'gender'                 => $validated['gender'],
                        'tshirt_size'            => $validated['tshirt_size'],
                        'experience'             => $validated['experience'],
                        'comfortable_fast_paced' => $validated['comfortable_fast_paced'],
                        'full_availability'      => $validated['full_availability'],
                        'contribution'           => $validated['contribution'],
                        'resume_link'            => $validated['resume_link'] ?? null,
                        'instagram'              => $validated['instagram'] ?? null,
                        'location'               => $validated['location'],
                    ];

                    $existingUser->volunteerProfile()->updateOrCreate(
                        ['user_id' => $existingUser->id],
                        $profileData,
                    );

                    // Asignar al evento
                    $existingUser->eventsAsVolunteer()->attach($eventId, [
                        'assigned_role' => 'volunteer',
                        'status'        => 'assigned',
                    ]);

                    // Generar pase
                    EventPass::create([
                        'event_id'     => $eventId,
                        'user_id'      => $existingUser->id,
                        'qr_code'      => EventPass::generateQrCode(),
                        'pass_type'    => 'volunteer',
                        'holder_name'  => $existingUser->full_name,
                        'holder_email' => $existingUser->email,
                        'issued_at'    => now(),
                        'status'       => 'active',
                    ]);

                    return $existingUser;
                }

                // Nuevo registro
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
                    'contribution'           => $validated['contribution'],
                    'resume_link'            => $validated['resume_link'] ?? null,
                    'instagram'              => $validated['instagram'] ?? null,
                    'location'               => $validated['location'],
                ]);

                // Asignar al evento
                $user->eventsAsVolunteer()->attach($eventId, [
                    'assigned_role' => 'volunteer',
                    'status'        => 'assigned',
                ]);

                // Generar pase
                EventPass::create([
                    'event_id'     => $eventId,
                    'user_id'      => $user->id,
                    'qr_code'      => EventPass::generateQrCode(),
                    'pass_type'    => 'volunteer',
                    'holder_name'  => $user->full_name,
                    'holder_email' => $user->email,
                    'issued_at'    => now(),
                    'status'       => 'active',
                ]);

                return $user;
            });

            // Si el voluntario estaba rejected, reactivar a applicant
            if ($isReRegistration && $user->status === 'rejected') {
                $user->update(['status' => 'applicant']);
            }

            $event = Event::find($eventId);
            $this->activityLog->log(
                ActivityAction::Registered,
                $user,
                null,
                ($isReRegistration ? 'Re-registro voluntario: ' : 'Registro voluntario: ')
                    . "{$user->first_name} {$user->last_name} para {$event->name}",
                ['source' => 'wordpress', 'event_id' => $event->id, 're_registration' => $isReRegistration]
            );

            // Notificar a admin y operation
            $notifyUsers = User::whereIn('role', ['admin', 'operation'])->get();
            foreach ($notifyUsers as $notifyUser) {
                $notifyUser->notify(new NewVolunteerRegistered($user, $event->name));
            }

            // Enviar email de confirmación (siempre, incluyendo re-registros para nuevo evento)
            SendVolunteerRegistrationEmailJob::dispatch($user->id, $event->name);

            $message = $isReRegistration
                ? 'You have been successfully registered for this new event!'
                : 'Your volunteer application has been received successfully. We will contact you soon!';

            return response()->json(['message' => $message], 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'An error occurred processing your application. Please try again.',
            ], 500);
        }
    }
}
