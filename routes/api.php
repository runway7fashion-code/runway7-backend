<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Auth pública — rate limited: 10 intentos por minuto
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/login', [App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('models/register', [App\Http\Controllers\Api\V1\ModelRegistrationController::class, 'store']);
        Route::get('models/events', [App\Http\Controllers\Api\V1\ModelRegistrationController::class, 'events']);
        Route::post('volunteers/register', [App\Http\Controllers\Api\V1\VolunteerRegistrationController::class, 'store']);
        Route::get('volunteers/events', [App\Http\Controllers\Api\V1\VolunteerRegistrationController::class, 'events']);
        Route::post('check-email', function (\Illuminate\Http\Request $request) {
            $request->validate(['email' => 'required|email', 'role' => 'required|string']);
            $user = \App\Models\User::withTrashed()->where('email', $request->email)->first();
            if ($user && $user->trashed()) {
                return response()->json(['available' => true]);
            }
            if (!$user) {
                return response()->json(['available' => true]);
            }
            if ($user->role !== $request->role) {
                return response()->json([
                    'available' => false,
                    'message' => 'This email is already registered as ' . $user->role . '. Please use a different email or contact us at operations@runway7fashion.com',
                ]);
            }
            return response()->json(['available' => true, 'existing' => true]);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [App\Http\Controllers\Api\V1\AuthController::class, 'me']);
        Route::post('auth/logout', [App\Http\Controllers\Api\V1\AuthController::class, 'logout']);

        // Chat
        Route::prefix('chat')->name('chat.')->group(function () {
            Route::get('conversations', [App\Http\Controllers\Api\V1\ChatController::class, 'conversations'])->name('conversations');
            Route::get('conversations/{conversation}', [App\Http\Controllers\Api\V1\ChatController::class, 'messages'])->name('messages');
            Route::post('conversations/{conversation}/messages', [App\Http\Controllers\Api\V1\ChatController::class, 'sendMessage'])->name('send-message');
            Route::post('conversations/{conversation}/read', [App\Http\Controllers\Api\V1\ChatController::class, 'markAsRead'])->name('mark-read');
        });

        // Banners
        Route::get('banners', [App\Http\Controllers\Api\V1\BannerController::class, 'index'])->name('banners');

        // Events / Fittings
        Route::get('events', [App\Http\Controllers\Api\V1\EventController::class, 'index'])->name('events.index');
        Route::get('events/{event}', [App\Http\Controllers\Api\V1\EventController::class, 'show'])->name('events.show');
        Route::get('my-fittings', [App\Http\Controllers\Api\V1\EventController::class, 'myFittings'])->name('my-fittings');

        // Shows
        Route::get('my-shows', [App\Http\Controllers\Api\V1\ShowController::class, 'myShows'])->name('shows.my');
        Route::post('shows/{show}/confirm', [App\Http\Controllers\Api\V1\ShowController::class, 'confirm'])->name('shows.confirm');
        Route::post('shows/{show}/reject', [App\Http\Controllers\Api\V1\ShowController::class, 'reject'])->name('shows.reject');

        // Payments
        Route::get('my-payments', [App\Http\Controllers\Api\V1\PaymentController::class, 'myPayments'])->name('payments.my');
        Route::get('my-payments/{plan}', [App\Http\Controllers\Api\V1\PaymentController::class, 'show'])->name('payments.show');

        // Tickets / Passes / Check-in
        Route::get('my-passes', [App\Http\Controllers\Api\V1\TicketController::class, 'myPasses'])->name('passes.my');
        Route::get('my-tickets', [App\Http\Controllers\Api\V1\TicketController::class, 'myTickets'])->name('tickets.my');
        Route::post('check-in/scan', [App\Http\Controllers\Api\V1\TicketController::class, 'scan'])->name('checkin.scan');

        // Profile
        Route::put('profile', [App\Http\Controllers\Api\V1\ProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/photo', [App\Http\Controllers\Api\V1\ProfileController::class, 'uploadPhoto'])->name('profile.photo');
        Route::post('profile/picture', [App\Http\Controllers\Api\V1\ProfileController::class, 'uploadProfilePicture'])->name('profile.picture');

        // Notifications / Device Tokens
        Route::post('device-tokens', [App\Http\Controllers\Api\V1\NotificationController::class, 'registerToken'])->name('device-tokens.register');
        Route::delete('device-tokens', [App\Http\Controllers\Api\V1\NotificationController::class, 'removeToken'])->name('device-tokens.remove');

        // Casting
        Route::get('my-casting', [App\Http\Controllers\Api\V1\CastingController::class, 'myCasting'])->name('my-casting');
        Route::post('events/{event}/casting/confirm', [App\Http\Controllers\Api\V1\CastingController::class, 'confirm'])->name('casting.confirm');
        Route::post('events/{event}/casting/reject', [App\Http\Controllers\Api\V1\CastingController::class, 'reject'])->name('casting.reject');
    });
});
