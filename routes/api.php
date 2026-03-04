<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Auth pública — rate limited: 10 intentos por minuto
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/login', [App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('auth/login-code', [App\Http\Controllers\Api\V1\AuthController::class, 'loginWithCode']);
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
        Route::get('my-fittings', [App\Http\Controllers\Api\V1\EventController::class, 'myFittings'])->name('my-fittings');
    });
});
