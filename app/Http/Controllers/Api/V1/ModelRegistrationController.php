<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModelRegistrationController extends Controller
{
    public function __construct(protected ModelService $modelService) {}

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
     * Registrar una modelo desde el formulario público de WordPress.
     */
    public function store(Request $request): JsonResponse
    {
        // Honeypot: si el campo oculto tiene valor, es bot
        if ($request->filled('website_url')) {
            return response()->json([
                'message' => 'Tu aplicación ha sido recibida exitosamente. ¡Te contactaremos pronto!',
            ], 201);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'required|string|unique:users,phone',
            'instagram'  => 'required|string|max:255',
            'age'        => 'required|integer|min:18|max:80',
            'gender'     => 'required|in:female,male,non_binary',
            'location'   => 'required|string|max:255',
            'ethnicity'  => 'required|in:asian,black,caucasian,hispanic,middle_eastern,mixed,other',
            'hair'       => 'required|in:black,brown,blonde,red,gray,other',
            'body_type'  => 'required|in:slim,athletic,average,curvy,plus_size',
            'height'     => 'required|numeric',
            'bust'       => 'required|numeric',
            'waist'      => 'required|numeric',
            'hips'       => 'required|numeric',
            'shoe_size'  => 'required|string|max:20',
            'dress_size' => 'required|string|max:20',
            'event_id'   => 'required|exists:events,id',

            'profile_picture' => 'required|image|max:1536',
            'photo_1'         => 'required|image|max:1536',
            'photo_2'         => 'required|image|max:1536',
            'photo_3'         => 'required|image|max:1536',
            'photo_4'         => 'required|image|max:1536',
        ], [
            'first_name.required'      => 'First name is required.',
            'last_name.required'       => 'Last name is required.',
            'email.required'           => 'Email is required.',
            'email.email'              => 'Please enter a valid email.',
            'email.unique'             => 'This email is already registered in our system.',
            'phone.required'           => 'Phone is required.',
            'phone.unique'             => 'This phone is already registered in our system.',
            'instagram.required'       => 'Instagram is required.',
            'age.required'             => 'Age is required.',
            'age.min'                  => 'You must be at least 18 years old.',
            'age.max'                  => 'Maximum age is 80.',
            'gender.required'          => 'Gender is required.',
            'location.required'        => 'Location is required.',
            'ethnicity.required'       => 'Ethnicity is required.',
            'hair.required'            => 'Hair color is required.',
            'body_type.required'       => 'Body type is required.',
            'height.required'          => 'Height is required.',
            'bust.required'            => 'Bust is required.',
            'waist.required'           => 'Waist is required.',
            'hips.required'            => 'Hips is required.',
            'shoe_size.required'       => 'Shoe size is required.',
            'dress_size.required'      => 'Dress size is required.',
            'event_id.required'        => 'Please select an event.',
            'event_id.exists'          => 'The selected event is not valid.',
            'profile_picture.required' => 'Profile photo is required.',
            'profile_picture.image'    => 'Profile photo must be an image.',
            'profile_picture.max'      => 'Profile photo must not exceed 1.5MB.',
            'photo_1.required'         => 'Headshot photo is required.',
            'photo_1.image'            => 'Headshot must be an image.',
            'photo_1.max'              => 'Headshot must not exceed 1.5MB.',
            'photo_2.required'         => 'Full body front photo is required.',
            'photo_2.image'            => 'Full body front must be an image.',
            'photo_2.max'              => 'Full body front must not exceed 1.5MB.',
            'photo_3.required'         => 'Full body side photo is required.',
            'photo_3.image'            => 'Full body side must be an image.',
            'photo_3.max'              => 'Full body side must not exceed 1.5MB.',
            'photo_4.required'         => 'Creative/Editorial photo is required.',
            'photo_4.image'            => 'Creative/Editorial must be an image.',
            'photo_4.max'              => 'Creative/Editorial must not exceed 1.5MB.',
        ]);

        try {
            $model = DB::transaction(function () use ($request, $validated) {
                $userData = collect($validated)->only(['first_name', 'last_name', 'email', 'phone'])->toArray();

                $profileData = collect($validated)->only([
                    'instagram', 'age', 'gender', 'location', 'ethnicity',
                    'hair', 'body_type', 'height', 'bust', 'waist', 'hips',
                    'shoe_size', 'dress_size',
                ])->toArray();

                $user = $this->modelService->createModel(
                    $userData, $profileData,
                    eventId: (int) $validated['event_id'],
                    status: 'applicant'
                );

                // Subir foto de perfil
                $this->modelService->uploadProfilePicture($user, $request->file('profile_picture'));

                // Subir comp card photos
                foreach (range(1, 4) as $position) {
                    $this->modelService->uploadCompCardPhoto($user, $position, $request->file("photo_{$position}"));
                }

                return $user;
            });

            return response()->json([
                'message' => 'Your application has been received successfully. We will contact you soon!',
            ], 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'An error occurred processing your application. Please try again.',
            ], 500);
        }
    }
}
