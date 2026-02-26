<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventDayController;
use App\Http\Controllers\Admin\ShowController;
use App\Http\Controllers\Admin\ModelController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\DesignerController;
use App\Http\Controllers\Admin\DesignerSettingsController;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// Admin routes
Route::prefix('admin')->name('admin.')->group(function () {
    // Login — rate limited: 5 intentos por minuto
    Route::middleware('throttle:5,1')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.post');
    });

    Route::middleware(['auth', 'internal'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        // Dashboard — cada rol ve su propio dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Usuarios - solo admin
        Route::middleware('section:users')->group(function () {
            Route::resource('users', UserController::class);
        });

        // Modelos - admin, operation
        Route::middleware('section:models')->group(function () {
            Route::resource('models', ModelController::class);
            Route::post('models/{model}/assign-event', [ModelController::class, 'assignEvent'])->name('models.assign-event');
            Route::delete('models/{model}/remove-event/{event}', [ModelController::class, 'removeEvent'])->name('models.remove-event');
            Route::post('models/{model}/upload-photo/{position}', [ModelController::class, 'uploadPhoto'])->name('models.upload-photo');
            Route::delete('models/{model}/delete-photo/{position}', [ModelController::class, 'deletePhoto'])->name('models.delete-photo');
            Route::post('models/{model}/send-welcome-email', [ModelController::class, 'sendWelcomeEmail'])->name('models.send-welcome-email');
            Route::post('models/{model}/upload-profile-picture', [ModelController::class, 'uploadProfilePicture'])->name('models.upload-profile-picture');
            Route::delete('models/{model}/delete-profile-picture', [ModelController::class, 'deleteProfilePicture'])->name('models.delete-profile-picture');
        });

        // Diseñadores - admin, operation, sales
        Route::middleware('section:designers')->group(function () {
            Route::resource('designers', DesignerController::class);
            Route::post('designers/{designer}/assign-event', [DesignerController::class, 'assignEvent'])->name('designers.assign-event');
            Route::delete('designers/{designer}/remove-event/{event}', [DesignerController::class, 'removeEvent'])->name('designers.remove-event');
            Route::post('designers/{designer}/assistants', [DesignerController::class, 'addAssistant'])->name('designers.add-assistant');
            Route::delete('designers/assistants/{assistant}', [DesignerController::class, 'removeAssistant'])->name('designers.remove-assistant');
            Route::delete('designers/{designer}/shows/{show}', [DesignerController::class, 'removeShow'])->name('designers.remove-show');
            Route::post('designers/{designer}/shows', [DesignerController::class, 'addShow'])->name('designers.add-show');
            Route::put('designer-materials/{material}', [DesignerController::class, 'updateMaterial'])->name('designers.update-material');
            Route::put('designer-displays/{display}', [DesignerController::class, 'updateDisplay'])->name('designers.update-display');
            Route::post('designer-displays/{display}/upload-video', [DesignerController::class, 'uploadVideo'])->name('designers.upload-video');
            Route::post('designer-displays/{display}/upload-audio', [DesignerController::class, 'uploadAudio'])->name('designers.upload-audio');
        });

        // Eventos - admin, operation
        Route::middleware('section:events')->group(function () {
            Route::resource('events', EventController::class);
            Route::post('events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');
            Route::post('events/{event}/generate-shows', [ShowController::class, 'generateShows'])->name('events.generate-shows');

            // Días del evento
            Route::post('events/{event}/days', [EventDayController::class, 'store'])->name('events.days.store');
            Route::put('events/{event}/days/{day}', [EventDayController::class, 'update'])->name('events.days.update');
            Route::delete('events/{event}/days/{day}', [EventDayController::class, 'destroy'])->name('events.days.destroy');
            Route::post('events/{event}/days/{day}/shows', [ShowController::class, 'store'])->name('events.days.shows.store');

            // Shows
            Route::put('shows/{show}', [ShowController::class, 'update'])->name('shows.update');
            Route::delete('shows/{show}', [ShowController::class, 'destroy'])->name('shows.destroy');
            Route::post('shows/{show}/assign-designer', [ShowController::class, 'assignDesigner'])->name('shows.assign-designer');
            Route::post('shows/{show}/remove-designer', [ShowController::class, 'removeDesigner'])->name('shows.remove-designer');
        });

        // Chats - admin, operation
        Route::middleware('section:chats')->group(function () {
            Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
            Route::get('chats/{conversation}', [ChatController::class, 'show'])->name('chats.show');
        });

        // Banners - admin, marketing
        Route::middleware('section:banners')->group(function () {
            Route::resource('banners', BannerController::class);
            Route::post('banners/{banner}/upload-image', [BannerController::class, 'uploadImage'])->name('banners.upload-image');
            Route::post('banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');
        });

        // Ajustes - solo admin
        Route::middleware('section:settings')->group(function () {
            Route::prefix('settings')->name('settings.')->group(function () {
                Route::get('designers', [DesignerSettingsController::class, 'index'])->name('designers');
                Route::post('designer-categories', [DesignerSettingsController::class, 'storeCategory'])->name('designer-categories.store');
                Route::put('designer-categories/{category}', [DesignerSettingsController::class, 'updateCategory'])->name('designer-categories.update');
                Route::delete('designer-categories/{category}', [DesignerSettingsController::class, 'destroyCategory'])->name('designer-categories.destroy');
                Route::post('designer-packages', [DesignerSettingsController::class, 'storePackage'])->name('designer-packages.store');
                Route::put('designer-packages/{package}', [DesignerSettingsController::class, 'updatePackage'])->name('designer-packages.update');
                Route::delete('designer-packages/{package}', [DesignerSettingsController::class, 'destroyPackage'])->name('designer-packages.destroy');
            });
        });
    });
});
