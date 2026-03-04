<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModelRegistrationController extends Controller
{
    public function __construct(protected ModelService $modelService) {}

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
            'phone'      => 'nullable|string|unique:users,phone',
            'instagram'  => 'nullable|string|max:255',
            'age'        => 'nullable|integer|min:16|max:80',
            'gender'     => 'nullable|in:female,male,non_binary',
            'location'   => 'nullable|string|max:255',
            'ethnicity'  => 'nullable|in:asian,black,caucasian,hispanic,middle_eastern,mixed,other',
            'hair'       => 'nullable|in:black,brown,blonde,red,gray,other',
            'body_type'  => 'nullable|in:slim,athletic,average,curvy,plus_size',
            'height'     => 'nullable|numeric',
            'bust'       => 'nullable|numeric',
            'waist'      => 'nullable|numeric',
            'hips'       => 'nullable|numeric',
            'shoe_size'  => 'nullable|string|max:20',
            'dress_size' => 'nullable|string|max:20',

            'profile_picture' => 'required|image|max:5120',
            'photo_1'         => 'nullable|image|max:5120',
            'photo_2'         => 'nullable|image|max:5120',
            'photo_3'         => 'nullable|image|max:5120',
            'photo_4'         => 'nullable|image|max:5120',
        ], [
            'first_name.required'      => 'El nombre es obligatorio.',
            'last_name.required'       => 'El apellido es obligatorio.',
            'email.required'           => 'El correo electrónico es obligatorio.',
            'email.email'              => 'El correo electrónico no es válido.',
            'email.unique'             => 'Este correo ya está registrado en nuestro sistema.',
            'phone.unique'             => 'Este teléfono ya está registrado en nuestro sistema.',
            'age.min'                  => 'La edad mínima es 16 años.',
            'age.max'                  => 'La edad máxima es 80 años.',
            'profile_picture.required' => 'La foto de perfil es obligatoria.',
            'profile_picture.image'    => 'La foto de perfil debe ser una imagen.',
            'profile_picture.max'      => 'La foto de perfil no debe superar 5MB.',
            'photo_1.image'            => 'La foto 1 debe ser una imagen.',
            'photo_1.max'              => 'La foto 1 no debe superar 5MB.',
            'photo_2.image'            => 'La foto 2 debe ser una imagen.',
            'photo_2.max'              => 'La foto 2 no debe superar 5MB.',
            'photo_3.image'            => 'La foto 3 debe ser una imagen.',
            'photo_3.max'              => 'La foto 3 no debe superar 5MB.',
            'photo_4.image'            => 'La foto 4 debe ser una imagen.',
            'photo_4.max'              => 'La foto 4 no debe superar 5MB.',
        ]);

        try {
            $model = DB::transaction(function () use ($request, $validated) {
                $userData = collect($validated)->only(['first_name', 'last_name', 'email', 'phone'])->toArray();

                $profileData = collect($validated)->only([
                    'instagram', 'age', 'gender', 'location', 'ethnicity',
                    'hair', 'body_type', 'height', 'bust', 'waist', 'hips',
                    'shoe_size', 'dress_size',
                ])->toArray();

                $user = $this->modelService->createModel($userData, $profileData, status: 'applicant');

                // Subir foto de perfil
                $this->modelService->uploadProfilePicture($user, $request->file('profile_picture'));

                // Subir comp card photos si existen
                foreach (range(1, 4) as $position) {
                    if ($request->hasFile("photo_{$position}")) {
                        $this->modelService->uploadCompCardPhoto($user, $position, $request->file("photo_{$position}"));
                    }
                }

                return $user;
            });

            return response()->json([
                'message' => 'Tu aplicación ha sido recibida exitosamente. ¡Te contactaremos pronto!',
            ], 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'Ocurrió un error procesando tu aplicación. Inténtalo de nuevo.',
            ], 500);
        }
    }
}
