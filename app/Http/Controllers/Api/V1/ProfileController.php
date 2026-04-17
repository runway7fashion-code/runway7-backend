<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Actualizar perfil según el rol del usuario.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;

        if ($role === 'model') {
            return $this->updateModelProfile($request, $user);
        }

        if ($role === 'designer') {
            return $this->updateDesignerProfile($request, $user);
        }

        // Campos básicos para cualquier rol
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:30',
        ]);

        $user->update($data);

        return response()->json(['message' => 'Perfil actualizado.', 'user' => $user->fresh()]);
    }

    /**
     * Subir/actualizar fotos del comp card (modelos).
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'model') {
            return response()->json(['message' => 'Solo modelos pueden subir comp card.'], 403);
        }

        $request->validate([
            'position' => 'required|integer|in:1,2,3,4',
            'photo' => 'required|image|max:1536', // 1.5MB
        ]);

        $profile = $user->modelProfile;
        if (!$profile) {
            return response()->json(['message' => 'Perfil de modelo no encontrado.'], 404);
        }

        $position = $request->input('position');
        $field = "photo_{$position}";

        // Eliminar foto anterior si existe
        if ($profile->$field) {
            Storage::disk('public')->delete($profile->$field);
        }

        $path = $request->file('photo')->store("models/{$user->id}/compcard", 'public');
        $profile->update([
            $field => $path,
            'compcard_completed' => $profile->isCompCardComplete(),
        ]);

        return response()->json([
            'message' => 'Foto actualizada.',
            'position' => $position,
            'url' => Storage::disk('public')->url($path),
            'comp_card_progress' => $profile->fresh()->comp_card_progress,
        ]);
    }

    /**
     * Subir/actualizar foto de perfil.
     */
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'photo' => 'required|image|max:1536',
        ]);

        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $folder = match ($user->role) {
            'model' => "models/{$user->id}",
            'designer' => "designers/{$user->id}",
            default => "users/{$user->id}",
        };

        $path = $request->file('photo')->store($folder, 'public');
        $user->update(['profile_picture' => $path]);

        return response()->json([
            'message' => 'Foto de perfil actualizada.',
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    private function updateModelProfile(Request $request, $user): JsonResponse
    {
        $userData = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:30',
        ]);

        $profileData = $request->validate([
            'instagram' => 'sometimes|nullable|string|max:100',
            'height' => 'sometimes|nullable|string|max:10',
            'bust' => 'sometimes|nullable|string|max:10',
            'chest' => 'sometimes|nullable|string|max:10',
            'waist' => 'sometimes|nullable|string|max:10',
            'hips' => 'sometimes|nullable|string|max:10',
            'shoe_size' => 'sometimes|nullable|string|max:10',
            'dress_size' => 'sometimes|nullable|string|max:10',
            'body_type' => 'sometimes|nullable|in:slim,athletic,average,curvy,plus_size',
            'ethnicity' => 'sometimes|nullable|in:asian,black,caucasian,hispanic,middle_eastern,mixed,other',
            'hair' => 'sometimes|nullable|in:black,brown,blonde,red,gray,other',
            'location' => 'sometimes|nullable|string|max:200',
        ]);

        if (!empty($userData)) {
            $user->update($userData);
        }

        if (!empty($profileData) && $user->modelProfile) {
            // Sanitizar instagram
            if (isset($profileData['instagram'])) {
                $profileData['instagram'] = ltrim(str_replace(['https://www.instagram.com/', 'https://instagram.com/', 'http://instagram.com/'], '', $profileData['instagram']), '@/');
            }
            $user->modelProfile->update($profileData);
        }

        return response()->json([
            'message' => 'Perfil actualizado.',
            'user' => $user->fresh()->load('modelProfile'),
        ]);
    }

    private function updateDesignerProfile(Request $request, $user): JsonResponse
    {
        $userData = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:30',
        ]);

        $profileData = $request->validate([
            'brand_name' => 'sometimes|nullable|string|max:200',
            'collection_name' => 'sometimes|nullable|string|max:200',
            'website' => 'sometimes|nullable|url|max:300',
            'instagram' => 'sometimes|nullable|string|max:100',
            'country' => 'sometimes|nullable|string|max:100',
        ]);

        if (!empty($userData)) {
            $user->update($userData);
        }

        if (!empty($profileData) && $user->designerProfile) {
            $user->designerProfile->update($profileData);
        }

        return response()->json([
            'message' => 'Perfil actualizado.',
            'user' => $user->fresh()->load('designerProfile'),
        ]);
    }
}
