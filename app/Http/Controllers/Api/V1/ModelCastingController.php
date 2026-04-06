<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DesignerModelFavorite;
use App\Models\Event;
use App\Models\Show;
use App\Services\CastingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModelCastingController extends Controller
{
    public function __construct(protected CastingService $castingService) {}

    /**
     * ALL MODELS — Modelos disponibles en el evento (checked_in en casting).
     * El designer ve estas modelos durante el día de casting.
     */
    public function availableModels(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        // Verify designer is assigned to this event with model_casting_enabled
        $eventDesigner = DB::table('event_designer')
            ->where('event_id', $event->id)
            ->where('designer_id', $user->id)
            ->first();

        if (!$eventDesigner) {
            return response()->json(['message' => 'You are not assigned to this event.'], 403);
        }

        if (!$eventDesigner->model_casting_enabled) {
            return response()->json(['message' => 'Model casting is not enabled for your account in this event.'], 403);
        }

        // Get models that have checked in to casting (casting_status = 'checked_in' or 'selected')
        // Also include 'scheduled' models that are confirmed to attend
        $models = DB::table('event_model')
            ->join('users', 'users.id', '=', 'event_model.model_id')
            ->leftJoin('model_profiles', 'model_profiles.user_id', '=', 'users.id')
            ->where('event_model.event_id', $event->id)
            ->whereIn('event_model.casting_status', ['checked_in', 'selected'])
            ->where('users.status', 'active')
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
                'users.profile_picture',
                'event_model.participation_number',
                'event_model.casting_status',
                'model_profiles.height',
                'model_profiles.bust',
                'model_profiles.waist',
                'model_profiles.hips',
                'model_profiles.shoe_size',
                'model_profiles.dress_size',
                'model_profiles.body_type',
                'model_profiles.ethnicity',
                'model_profiles.hair',
                'model_profiles.gender',
                'model_profiles.age',
                'model_profiles.instagram',
                'model_profiles.photo_1',
                'model_profiles.photo_2',
                'model_profiles.photo_3',
                'model_profiles.photo_4',
                'model_profiles.agency',
                'model_profiles.is_agency',
            ])
            ->orderBy('event_model.participation_number')
            ->get();

        // Get this designer's favorites for quick lookup
        $favoriteModelIds = DesignerModelFavorite::where('designer_id', $user->id)
            ->where('event_id', $event->id)
            ->pluck('model_id')
            ->toArray();

        // Get this designer's existing requests for these models
        $designerShows = DB::table('show_designer')
            ->join('shows', 'shows.id', '=', 'show_designer.show_id')
            ->join('event_days', 'event_days.id', '=', 'shows.event_day_id')
            ->where('event_days.event_id', $event->id)
            ->where('show_designer.designer_id', $user->id)
            ->pluck('shows.id')
            ->toArray();

        $existingRequests = DB::table('show_model')
            ->whereIn('show_id', $designerShows)
            ->where('designer_id', $user->id)
            ->get()
            ->groupBy('model_id');

        $data = $models->map(function ($model) use ($favoriteModelIds, $existingRequests) {
            $requests = $existingRequests->get($model->id, collect());
            $requestStatus = null;
            if ($requests->isNotEmpty()) {
                // Priority: confirmed > requested > rejected
                if ($requests->contains('status', 'confirmed')) {
                    $requestStatus = 'confirmed';
                } elseif ($requests->contains('status', 'requested')) {
                    $requestStatus = 'requested';
                } elseif ($requests->contains('status', 'rejected')) {
                    $requestStatus = 'rejected';
                }
            }

            return [
                'id' => $model->id,
                'first_name' => $model->first_name,
                'last_name' => $model->last_name,
                'email' => $model->email,
                'phone' => $model->phone,
                'profile_picture' => $model->profile_picture,
                'participation_number' => $model->participation_number,
                'casting_status' => $model->casting_status,
                'is_favorite' => in_array($model->id, $favoriteModelIds),
                'request_status' => $requestStatus,
                'measurements' => [
                    'height' => $model->height,
                    'bust' => $model->bust,
                    'waist' => $model->waist,
                    'hips' => $model->hips,
                    'shoe_size' => $model->shoe_size,
                    'dress_size' => $model->dress_size,
                ],
                'profile' => [
                    'body_type' => $model->body_type,
                    'ethnicity' => $model->ethnicity,
                    'hair' => $model->hair,
                    'gender' => $model->gender,
                    'age' => $model->age,
                    'instagram' => $model->instagram,
                    'agency' => $model->agency,
                    'is_agency' => (bool) $model->is_agency,
                ],
                'photos' => array_values(array_filter([
                    $model->photo_1,
                    $model->photo_2,
                    $model->photo_3,
                    $model->photo_4,
                ])),
            ];
        });

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * TOGGLE FAVORITE — Agregar o quitar modelo de favoritos.
     */
    public function toggleFavorite(Request $request, Event $event, int $modelId): JsonResponse
    {
        $user = $request->user();

        // Verify model exists in this event
        $exists = DB::table('event_model')
            ->where('event_id', $event->id)
            ->where('model_id', $modelId)
            ->exists();

        if (!$exists) {
            return response()->json(['message' => 'Model not found in this event.'], 404);
        }

        $favorite = DesignerModelFavorite::where('designer_id', $user->id)
            ->where('model_id', $modelId)
            ->where('event_id', $event->id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['is_favorite' => false, 'message' => 'Removed from favorites.']);
        }

        DesignerModelFavorite::create([
            'designer_id' => $user->id,
            'model_id' => $modelId,
            'event_id' => $event->id,
        ]);

        return response()->json(['is_favorite' => true, 'message' => 'Added to favorites.']);
    }

    /**
     * MY FAVORITES — Modelos marcadas como favoritas por el designer en este evento.
     */
    public function myFavorites(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $favoriteModelIds = DesignerModelFavorite::where('designer_id', $user->id)
            ->where('event_id', $event->id)
            ->pluck('model_id');

        if ($favoriteModelIds->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $models = DB::table('event_model')
            ->join('users', 'users.id', '=', 'event_model.model_id')
            ->leftJoin('model_profiles', 'model_profiles.user_id', '=', 'users.id')
            ->where('event_model.event_id', $event->id)
            ->whereIn('event_model.model_id', $favoriteModelIds)
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
                'users.profile_picture',
                'event_model.participation_number',
                'event_model.casting_status',
                'model_profiles.height',
                'model_profiles.bust',
                'model_profiles.waist',
                'model_profiles.hips',
                'model_profiles.shoe_size',
                'model_profiles.dress_size',
                'model_profiles.body_type',
                'model_profiles.ethnicity',
                'model_profiles.hair',
                'model_profiles.gender',
                'model_profiles.age',
                'model_profiles.instagram',
                'model_profiles.photo_1',
                'model_profiles.photo_2',
                'model_profiles.photo_3',
                'model_profiles.photo_4',
                'model_profiles.agency',
                'model_profiles.is_agency',
            ])
            ->orderBy('event_model.participation_number')
            ->get();

        // Get request statuses
        $designerShows = DB::table('show_designer')
            ->join('shows', 'shows.id', '=', 'show_designer.show_id')
            ->join('event_days', 'event_days.id', '=', 'shows.event_day_id')
            ->where('event_days.event_id', $event->id)
            ->where('show_designer.designer_id', $user->id)
            ->pluck('shows.id')
            ->toArray();

        $existingRequests = DB::table('show_model')
            ->whereIn('show_id', $designerShows)
            ->where('designer_id', $user->id)
            ->get()
            ->groupBy('model_id');

        $data = $models->map(function ($model) use ($existingRequests) {
            $requests = $existingRequests->get($model->id, collect());
            $requestStatus = null;
            if ($requests->isNotEmpty()) {
                if ($requests->contains('status', 'confirmed')) $requestStatus = 'confirmed';
                elseif ($requests->contains('status', 'requested')) $requestStatus = 'requested';
                elseif ($requests->contains('status', 'rejected')) $requestStatus = 'rejected';
            }

            return [
                'id' => $model->id,
                'first_name' => $model->first_name,
                'last_name' => $model->last_name,
                'email' => $model->email,
                'phone' => $model->phone,
                'profile_picture' => $model->profile_picture,
                'participation_number' => $model->participation_number,
                'is_favorite' => true,
                'request_status' => $requestStatus,
                'measurements' => [
                    'height' => $model->height,
                    'bust' => $model->bust,
                    'waist' => $model->waist,
                    'hips' => $model->hips,
                    'shoe_size' => $model->shoe_size,
                    'dress_size' => $model->dress_size,
                ],
                'profile' => [
                    'body_type' => $model->body_type,
                    'ethnicity' => $model->ethnicity,
                    'hair' => $model->hair,
                    'gender' => $model->gender,
                    'age' => $model->age,
                    'instagram' => $model->instagram,
                    'agency' => $model->agency,
                    'is_agency' => (bool) $model->is_agency,
                ],
                'photos' => array_values(array_filter([
                    $model->photo_1,
                    $model->photo_2,
                    $model->photo_3,
                    $model->photo_4,
                ])),
            ];
        });

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * REQUEST MODEL — Designer solicita una modelo para su show.
     * Usa CastingService::requestModelForShow() que ya tiene toda la lógica:
     * - Valida que el designer esté asignado al show
     * - Valida que no haya solicitud duplicada
     * - Valida conflicto de shows consecutivos
     */
    public function requestModel(Request $request, Show $show): JsonResponse
    {
        $request->validate([
            'model_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $model = \App\Models\User::findOrFail($request->model_id);

        // Verify designer has model_casting_enabled for this event
        $show->loadMissing('eventDay');
        $eventId = $show->eventDay->event_id;

        $eventDesigner = DB::table('event_designer')
            ->where('event_id', $eventId)
            ->where('designer_id', $user->id)
            ->first();

        if (!$eventDesigner || !$eventDesigner->model_casting_enabled) {
            return response()->json(['message' => 'Model casting is not enabled for your account.'], 403);
        }

        // Verify model is checked_in to the event casting
        $eventModel = DB::table('event_model')
            ->where('event_id', $eventId)
            ->where('model_id', $model->id)
            ->whereIn('casting_status', ['checked_in', 'selected'])
            ->first();

        if (!$eventModel) {
            return response()->json(['message' => 'This model is not available for casting.'], 422);
        }

        try {
            $result = $this->castingService->requestModelForShow($show, $model, $user, $request->input('message'));
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * MY REQUESTS — Solicitudes enviadas por el designer con su estado.
     */
    public function myRequests(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $requests = DB::table('show_model')
            ->join('shows', 'shows.id', '=', 'show_model.show_id')
            ->join('event_days', 'event_days.id', '=', 'shows.event_day_id')
            ->join('users', 'users.id', '=', 'show_model.model_id')
            ->leftJoin('model_profiles', 'model_profiles.user_id', '=', 'users.id')
            ->leftJoin('event_model', function ($join) use ($event) {
                $join->on('event_model.model_id', '=', 'show_model.model_id')
                    ->where('event_model.event_id', '=', $event->id);
            })
            ->where('event_days.event_id', $event->id)
            ->where('show_model.designer_id', $user->id)
            ->select([
                'show_model.id as request_id',
                'show_model.status',
                'show_model.requested_at',
                'show_model.responded_at',
                'show_model.confirmed_at',
                'show_model.rejection_reason',
                'shows.id as show_id',
                'shows.name as show_name',
                'shows.scheduled_time',
                'event_days.date as show_date',
                'event_days.label as day_label',
                'users.id as model_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
                'users.profile_picture',
                'event_model.participation_number',
                'model_profiles.height',
                'model_profiles.bust',
                'model_profiles.waist',
                'model_profiles.hips',
                'model_profiles.instagram',
                'model_profiles.photo_1',
            ])
            ->orderByDesc('show_model.requested_at')
            ->get();

        $data = $requests->map(function ($r) {
            return [
                'request_id' => $r->request_id,
                'status' => $r->status,
                'requested_at' => $r->requested_at,
                'responded_at' => $r->responded_at,
                'confirmed_at' => $r->confirmed_at,
                'rejection_reason' => $r->rejection_reason,
                'show' => [
                    'id' => $r->show_id,
                    'name' => $r->show_name,
                    'scheduled_time' => $r->scheduled_time,
                    'date' => $r->show_date,
                    'day_label' => $r->day_label,
                ],
                'model' => [
                    'id' => $r->model_id,
                    'first_name' => $r->first_name,
                    'last_name' => $r->last_name,
                    'email' => $r->email,
                    'phone' => $r->phone,
                    'profile_picture' => $r->profile_picture,
                    'participation_number' => $r->participation_number,
                    'height' => $r->height,
                    'bust' => $r->bust,
                    'waist' => $r->waist,
                    'hips' => $r->hips,
                    'instagram' => $r->instagram,
                    'photo' => $r->photo_1,
                ],
            ];
        });

        // Stats
        $stats = [
            'total' => $data->count(),
            'requested' => $data->where('status', 'requested')->count(),
            'confirmed' => $data->where('status', 'confirmed')->count(),
            'rejected' => $data->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'data' => $data,
            'stats' => $stats,
        ]);
    }

    /**
     * MY MODELS — Modelos confirmadas para los shows del designer.
     */
    public function myModels(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $confirmed = DB::table('show_model')
            ->join('shows', 'shows.id', '=', 'show_model.show_id')
            ->join('event_days', 'event_days.id', '=', 'shows.event_day_id')
            ->join('users', 'users.id', '=', 'show_model.model_id')
            ->leftJoin('model_profiles', 'model_profiles.user_id', '=', 'users.id')
            ->leftJoin('event_model', function ($join) use ($event) {
                $join->on('event_model.model_id', '=', 'show_model.model_id')
                    ->where('event_model.event_id', '=', $event->id);
            })
            ->where('event_days.event_id', $event->id)
            ->where('show_model.designer_id', $user->id)
            ->where('show_model.status', 'confirmed')
            ->select([
                'show_model.walk_order',
                'show_model.confirmed_at',
                'shows.id as show_id',
                'shows.name as show_name',
                'shows.scheduled_time',
                'event_days.date as show_date',
                'event_days.label as day_label',
                'users.id as model_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
                'users.profile_picture',
                'event_model.participation_number',
                'model_profiles.height',
                'model_profiles.bust',
                'model_profiles.waist',
                'model_profiles.hips',
                'model_profiles.shoe_size',
                'model_profiles.dress_size',
                'model_profiles.instagram',
                'model_profiles.photo_1',
                'model_profiles.photo_2',
                'model_profiles.photo_3',
                'model_profiles.photo_4',
            ])
            ->orderBy('shows.scheduled_time')
            ->orderBy('show_model.walk_order')
            ->get();

        $data = $confirmed->map(function ($r) {
            return [
                'show' => [
                    'id' => $r->show_id,
                    'name' => $r->show_name,
                    'scheduled_time' => $r->scheduled_time,
                    'date' => $r->show_date,
                    'day_label' => $r->day_label,
                ],
                'model' => [
                    'id' => $r->model_id,
                    'first_name' => $r->first_name,
                    'last_name' => $r->last_name,
                    'email' => $r->email,
                    'phone' => $r->phone,
                    'profile_picture' => $r->profile_picture,
                    'participation_number' => $r->participation_number,
                    'height' => $r->height,
                    'bust' => $r->bust,
                    'waist' => $r->waist,
                    'hips' => $r->hips,
                    'shoe_size' => $r->shoe_size,
                    'dress_size' => $r->dress_size,
                    'instagram' => $r->instagram,
                    'photos' => array_values(array_filter([
                        $r->photo_1,
                        $r->photo_2,
                        $r->photo_3,
                        $r->photo_4,
                    ])),
                ],
                'walk_order' => $r->walk_order,
                'confirmed_at' => $r->confirmed_at,
            ];
        });

        // Group by show for easier rendering
        $byShow = $data->groupBy('show.id')->map(function ($models, $showId) {
            $first = $models->first();
            return [
                'show' => $first['show'],
                'models' => $models->map(fn($m) => [
                    'model' => $m['model'],
                    'walk_order' => $m['walk_order'],
                    'confirmed_at' => $m['confirmed_at'],
                ])->values(),
                'model_count' => $models->count(),
            ];
        })->values();

        return response()->json([
            'data' => $byShow,
            'total_models' => $data->count(),
        ]);
    }

    /**
     * DESIGNER SHOWS — Shows asignados al designer (para el selector al solicitar modelo).
     */
    public function myShows(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $shows = DB::table('show_designer')
            ->join('shows', 'shows.id', '=', 'show_designer.show_id')
            ->join('event_days', 'event_days.id', '=', 'shows.event_day_id')
            ->where('event_days.event_id', $event->id)
            ->where('show_designer.designer_id', $user->id)
            ->whereIn('show_designer.status', ['assigned', 'confirmed'])
            ->select([
                'shows.id',
                'shows.name',
                'shows.scheduled_time',
                'shows.status',
                'event_days.date',
                'event_days.label as day_label',
                'show_designer.collection_name',
            ])
            ->orderBy('event_days.date')
            ->orderBy('shows.scheduled_time')
            ->get();

        return response()->json(['data' => $shows]);
    }
}
