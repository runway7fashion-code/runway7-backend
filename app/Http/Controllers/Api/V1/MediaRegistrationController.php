<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Jobs\SendMediaRegistrationEmailJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\EventPass;
use App\Models\User;
use App\Notifications\NewMediaRegistered;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MediaRegistrationController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLog,
    ) {}

    public function events(): JsonResponse
    {
        $events = Event::where('status', 'active')
            ->orderBy('start_date')
            ->get(['id', 'name', 'city', 'start_date', 'end_date'])
            ->map(fn($e) => [
                'id'         => $e->id,
                'name'       => $e->name,
                'city'       => $e->city,
                'start_date' => $e->start_date?->format('Y-m-d'),
                'end_date'   => $e->end_date?->format('Y-m-d'),
            ]);

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        // Honeypot
        if ($request->filled('website_url')) {
            return response()->json([
                'message' => 'Your application has been received successfully!',
            ], 201);
        }

        // Sanitize Instagram
        if ($request->filled('instagram')) {
            $ig = $request->input('instagram');
            $ig = strtok($ig, '?');
            $ig = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $ig);
            $ig = rtrim($ig, '/');
            $ig = ltrim($ig, '@');
            $request->merge(['instagram' => $ig]);
        }

        $existingUser = User::where('email', $request->input('email'))->first();

        $validated = $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email',
            'phone'         => 'required|string',
            'category'      => 'required|in:videographer,photographer',
            'portfolio_url' => 'required|url|max:500',
            'instagram'     => 'required|string|max:255',
            'location'      => 'required|string|max:255',
            'will_travel'   => 'required|in:yes,no',
            'event_id'      => 'required|exists:events,id',
        ], [
            'first_name.required' => 'First name is required.',
            'last_name.required'  => 'Last name is required.',
            'email.required'      => 'Email is required.',
            'email.email'         => 'Please enter a valid email.',
            'phone.required'      => 'Phone is required.',
            'category.required'   => 'Category is required.',
            'portfolio_url.required' => 'Portfolio link is required.',
            'portfolio_url.url'   => 'Please enter a valid URL.',
            'instagram.required'  => 'Instagram is required.',
            'location.required'   => 'Location is required.',
            'will_travel.required' => 'This field is required.',
            'event_id.required'   => 'Please select an event.',
            'event_id.exists'     => 'The selected event is not valid.',
        ]);

        $eventId = (int) $validated['event_id'];

        // Block inactive users
        if ($existingUser && $existingUser->status === 'inactive') {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact us for assistance.',
                'errors' => ['email' => ['Your account has been deactivated. Please contact us at operations@runway7fashion.com']],
            ], 422);
        }

        // Reject if email exists with different role
        if ($existingUser && $existingUser->role !== 'media') {
            return response()->json([
                'message' => 'This email is already registered with a different role.',
                'errors' => ['email' => ['This email is already registered as ' . $existingUser->role . '. Please use a different email or contact us at operations@runway7fashion.com']],
            ], 422);
        }

        // Check duplicate event
        if ($existingUser) {
            $alreadyInEvent = DB::table('event_media')
                ->where('media_id', $existingUser->id)
                ->where('event_id', $eventId)
                ->exists();

            if ($alreadyInEvent) {
                return response()->json([
                    'message' => 'You are already registered for this event.',
                    'errors' => ['email' => ['You are already registered for this event. Please select a different event or contact us at operations@runway7fashion.com']],
                ], 422);
            }
        }

        try {
            $isReRegistration = (bool) $existingUser;

            $user = DB::transaction(function () use ($validated, $existingUser, $eventId) {
                if ($existingUser) {
                    $existingUser->update([
                        'first_name' => $validated['first_name'],
                        'last_name'  => $validated['last_name'],
                        'phone'      => $validated['phone'],
                    ]);

                    $existingUser->mediaProfile()->updateOrCreate(
                        ['user_id' => $existingUser->id],
                        [
                            'category'      => $validated['category'],
                            'portfolio_url' => $validated['portfolio_url'],
                            'instagram'     => $validated['instagram'],
                            'location'      => $validated['location'],
                            'will_travel'   => $validated['will_travel'],
                        ],
                    );

                    // Assign to event
                    $existingUser->eventsAsMedia()->attach($eventId, [
                        'status' => 'assigned',
                    ]);

                    // Generate pass
                    EventPass::create([
                        'event_id'     => $eventId,
                        'user_id'      => $existingUser->id,
                        'qr_code'      => EventPass::generateQrCode(),
                        'pass_type'    => 'media',
                        'holder_name'  => $existingUser->full_name,
                        'holder_email' => $existingUser->email,
                        'issued_at'    => now(),
                        'status'       => 'active',
                    ]);

                    return $existingUser;
                }

                // New registration
                $user = User::create([
                    'first_name' => $validated['first_name'],
                    'last_name'  => $validated['last_name'],
                    'email'      => $validated['email'],
                    'phone'      => $validated['phone'],
                    'role'       => 'media',
                    'status'     => 'applicant',
                    'password'   => Hash::make('runway7'),
                ]);

                $user->mediaProfile()->create([
                    'category'      => $validated['category'],
                    'portfolio_url' => $validated['portfolio_url'],
                    'instagram'     => $validated['instagram'],
                    'location'      => $validated['location'],
                    'will_travel'   => $validated['will_travel'],
                ]);

                // Assign to event
                $user->eventsAsMedia()->attach($eventId, [
                    'status' => 'assigned',
                ]);

                // Generate pass
                EventPass::create([
                    'event_id'     => $eventId,
                    'user_id'      => $user->id,
                    'qr_code'      => EventPass::generateQrCode(),
                    'pass_type'    => 'media',
                    'holder_name'  => $user->full_name,
                    'holder_email' => $user->email,
                    'issued_at'    => now(),
                    'status'       => 'active',
                ]);

                return $user;
            });

            // Reactivate rejected users
            if ($isReRegistration && $user->status === 'rejected') {
                $user->update(['status' => 'applicant']);
            }

            $event = Event::find($eventId);
            $this->activityLog->log(
                ActivityAction::Registered,
                $user,
                null,
                ($isReRegistration ? 'Re-registro media: ' : 'Registro media: ')
                    . "{$user->first_name} {$user->last_name} para {$event->name}",
                ['source' => 'wordpress', 'event_id' => $event->id, 're_registration' => $isReRegistration]
            );

            // Notify admin and operation
            $notifyUsers = User::whereIn('role', ['admin', 'operation'])->get();
            foreach ($notifyUsers as $notifyUser) {
                $notifyUser->notify(new NewMediaRegistered($user, $event->name));
            }

            // Send registration email
            $log = CommunicationLog::create([
                'user_id'  => $user->id,
                'sent_by'  => null,
                'type'     => 'email',
                'channel'  => 'media_registration',
                'status'   => 'queued',
            ]);

            SendMediaRegistrationEmailJob::dispatch($user->id, $event->name, logId: $log->id);

            $message = $isReRegistration
                ? 'You have been successfully registered for this new event!'
                : 'Your media application has been received successfully. We will contact you soon!';

            return response()->json(['message' => $message], 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'An error occurred processing your application. Please try again.',
            ], 500);
        }
    }
}
