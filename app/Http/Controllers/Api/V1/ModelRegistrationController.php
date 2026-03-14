<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Jobs\SendRegistrationEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\Event;
use App\Models\User;
use App\Notifications\NewModelRegistered;
use App\Services\ActivityLogService;
use App\Services\ModelService;
use App\Services\ShopifyOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModelRegistrationController extends Controller
{
    public function __construct(
        protected ModelService $modelService,
        protected ActivityLogService $activityLog,
        protected ShopifyOrderService $shopifyService,
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
     * Registrar una modelo desde el formulario público de WordPress.
     * Si el email ya existe y la modelo NO está en el evento solicitado, se actualiza y asigna.
     * Si ya está en el mismo evento, se rechaza.
     */
    public function store(Request $request): JsonResponse
    {
        // Honeypot: si el campo oculto tiene valor, es bot
        if ($request->filled('website_url')) {
            return response()->json([
                'message' => 'Tu aplicación ha sido recibida exitosamente. ¡Te contactaremos pronto!',
            ], 201);
        }

        // Sanitizar Instagram: extraer solo el username sin URL, @, ni query params
        if ($request->filled('instagram')) {
            $ig = $request->input('instagram');
            $ig = strtok($ig, '?'); // Quitar query params (?igsh=...)
            $ig = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $ig);
            $ig = rtrim($ig, '/');
            $ig = ltrim($ig, '@');
            $request->merge(['instagram' => $ig]);
        }

        // Determinar si es re-registro (email ya existe)
        $existingUser = User::where('email', $request->input('email'))->first();

        // Fotos: profile_picture y photo_1 obligatorias para nuevos registros, el resto siempre opcional
        $requiredPhotoRule = $existingUser ? 'nullable|image|max:1536' : 'required|image|max:1536';
        $optionalPhotoRule = 'nullable|image|max:1536';

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email',
            'phone'      => 'required|string',
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
            'dress_size' => 'required|in:XXS,XS,S,M,L,XL,XXL',
            'event_id'   => 'required|exists:events,id',
            'agency_name'  => 'nullable|string|max:255',
            'order_number' => 'nullable|string|max:50',

            'profile_picture' => $requiredPhotoRule,
            'photo_1'         => $requiredPhotoRule,
            'photo_2'         => $optionalPhotoRule,
            'photo_3'         => $optionalPhotoRule,
            'photo_4'         => $optionalPhotoRule,
        ], [
            'first_name.required'      => 'First name is required.',
            'last_name.required'       => 'Last name is required.',
            'email.required'           => 'Email is required.',
            'email.email'              => 'Please enter a valid email.',
            'phone.required'           => 'Phone is required.',
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
            'photo_2.image'            => 'Full body front must be an image.',
            'photo_2.max'              => 'Full body front must not exceed 1.5MB.',
            'photo_3.image'            => 'Full body side must be an image.',
            'photo_3.max'              => 'Full body side must not exceed 1.5MB.',
            'photo_4.image'            => 'Creative/Editorial must be an image.',
            'photo_4.max'              => 'Creative/Editorial must not exceed 1.5MB.',
        ]);

        $eventId = (int) $validated['event_id'];

        // Si la modelo ya existe, verificar si ya está asignada al mismo evento
        if ($existingUser) {
            $alreadyInEvent = DB::table('event_model')
                ->where('model_id', $existingUser->id)
                ->where('event_id', $eventId)
                ->exists();

            if ($alreadyInEvent) {
                return response()->json([
                    'message' => 'You are already registered for this event.',
                    'errors' => ['email' => ['You are already registered for this event. Please select a different event or contact us at models@runway7fashion.com']],
                ], 422);
            }
        }

        $orderNumber = $validated['order_number'] ?? null;
        $hasValidOrder = false;

        // Si proporcionó order number, validar antes de crear la modelo
        if ($orderNumber) {
            // Verificar que no haya sido usado previamente
            $alreadyUsed = DB::table('event_model')
                ->where('shopify_order_number', ltrim(trim($orderNumber), '#'))
                ->exists();

            if ($alreadyUsed) {
                return response()->json([
                    'message' => 'This order number has already been used for a registration.',
                    'errors' => ['order_number' => ['This order number has already been used for a registration.']],
                ], 422);
            }

            $result = $this->shopifyService->validatePaidOrder($orderNumber);

            if (!$result['valid']) {
                return response()->json([
                    'message' => $result['reason'],
                    'errors' => ['order_number' => [$result['reason']]],
                ], 422);
            }

            $hasValidOrder = true;
            $orderNumber = ltrim(trim($orderNumber), '#');
        }

        try {
            $status = $hasValidOrder ? 'pending' : 'applicant';
            $isReRegistration = (bool) $existingUser;

            $model = DB::transaction(function () use ($request, $validated, $status, $orderNumber, $existingUser, $eventId) {
                $userData = collect($validated)->only(['first_name', 'last_name', 'email', 'phone'])->toArray();

                $profileData = collect($validated)->only([
                    'instagram', 'age', 'gender', 'location', 'ethnicity',
                    'hair', 'body_type', 'height', 'bust', 'waist', 'hips',
                    'shoe_size', 'dress_size',
                ])->toArray();

                if (!empty($validated['agency_name'])) {
                    $profileData['agency'] = $validated['agency_name'];
                    $profileData['is_agency'] = true;
                }

                if ($existingUser) {
                    // Re-registro: actualizar datos y asignar al nuevo evento
                    $user = $this->modelService->updateModel($existingUser, $userData, $profileData);
                    $this->modelService->assignToEvent($user, $eventId, shopifyOrderNumber: $orderNumber);

                    // Actualizar fotos solo si se proporcionaron nuevas
                    if ($request->hasFile('profile_picture')) {
                        $this->modelService->uploadProfilePicture($user, $request->file('profile_picture'));
                    }
                    foreach (range(1, 4) as $position) {
                        if ($request->hasFile("photo_{$position}")) {
                            $this->modelService->uploadCompCardPhoto($user, $position, $request->file("photo_{$position}"));
                        }
                    }

                    return $user;
                }

                // Nuevo registro
                $user = $this->modelService->createModel(
                    $userData, $profileData,
                    eventId: $eventId,
                    status: $status,
                    shopifyOrderNumber: $orderNumber,
                );

                // Subir foto de perfil
                $this->modelService->uploadProfilePicture($user, $request->file('profile_picture'));

                // Subir comp card photos (solo las que se enviaron)
                foreach (range(1, 4) as $position) {
                    if ($request->hasFile("photo_{$position}")) {
                        $this->modelService->uploadCompCardPhoto($user, $position, $request->file("photo_{$position}"));
                    }
                }

                return $user;
            });

            // Auto-assign casting slot según prioridad:
            // Slot 1: merch (Shopify order) o agencia prioritaria (Fanny/CG/FMA)
            // Slot 3+: agencia no prioritaria o sin agencia
            $hasAgency = !empty($validated['agency_name']);
            if ($hasValidOrder) {
                $this->modelService->autoAssignCastingSlot($model, $eventId, startFromPosition: 1);
            } elseif ($hasAgency) {
                $isPriorityAgency = (bool) preg_match('/\b(fanny|cg|fma|fanny\'s|cgmodels)\b/i', $validated['agency_name']);
                $slotPosition = $isPriorityAgency ? 1 : 3;
                $this->modelService->autoAssignCastingSlot($model, $eventId, startFromPosition: $slotPosition);
            } else {
                $this->modelService->autoAssignCastingSlot($model, $eventId, startFromPosition: 3);
            }

            $event = Event::find($eventId);
            $this->activityLog->log(
                ActivityAction::Registered,
                $model,
                null,
                ($isReRegistration ? 'Re-registro público: ' : 'Registro público: ')
                    . "{$model->first_name} {$model->last_name} para {$event->name}"
                    . ($hasValidOrder ? " (Shopify order #{$orderNumber})" : ''),
                ['source' => 'wordpress', 'event_id' => $event->id, 'shopify_order' => $orderNumber, 're_registration' => $isReRegistration]
            );

            $notifyUsers = User::whereIn('role', ['admin', 'operation'])->get();
            foreach ($notifyUsers as $notifyUser) {
                $notifyUser->notify(new NewModelRegistered($model, $event->name, $hasValidOrder));
            }

            if ($hasValidOrder) {
                // Fast-track: enviar welcome email con credenciales
                $castingDay = $event->eventDays()->where('type', 'casting')->first();
                SendWelcomeEmailJob::dispatch(
                    $model->id,
                    $event->name,
                    castingDate: $castingDay?->date,
                );
            } elseif (!$isReRegistration) {
                // Flujo normal (solo nuevos): enviar email de registro
                SendRegistrationEmailJob::dispatch($model->id, $event->name);
            }

            $message = $isReRegistration
                ? 'You have been successfully registered for this new event!'
                : ($hasValidOrder
                    ? 'Your registration is confirmed! Check your email for login details.'
                    : 'Your application has been received successfully. We will contact you soon!');

            return response()->json(['message' => $message], 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'An error occurred processing your application. Please try again.',
            ], 500);
        }
    }
}
