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
use App\Http\Controllers\Admin\AccountingController;
use App\Http\Controllers\Admin\PassController;
use App\Http\Controllers\Admin\VolunteerController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\SalesController;

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
            // Rutas sin {model} deben ir antes del resource
            Route::get('models/export', [ModelController::class, 'exportModels'])->name('models.export');
            Route::post('models/import', [ModelController::class, 'importModels'])->name('models.import');
            Route::get('models/import-template', [ModelController::class, 'downloadImportTemplate'])->name('models.import-template');
            Route::post('models/send-pending-emails', [ModelController::class, 'sendPendingWelcomeEmails'])->name('models.send-pending-emails');
            Route::resource('models', ModelController::class);
            Route::post('models/{model}/assign-event', [ModelController::class, 'assignEvent'])->name('models.assign-event');
            Route::delete('models/{model}/remove-event/{event}', [ModelController::class, 'removeEvent'])->name('models.remove-event');
            Route::post('models/{model}/upload-photo/{position}', [ModelController::class, 'uploadPhoto'])->name('models.upload-photo');
            Route::delete('models/{model}/delete-photo/{position}', [ModelController::class, 'deletePhoto'])->name('models.delete-photo');
            Route::post('models/{model}/send-welcome-email', [ModelController::class, 'sendWelcomeEmail'])->name('models.send-welcome-email');
            Route::post('models/{model}/upload-profile-picture', [ModelController::class, 'uploadProfilePicture'])->name('models.upload-profile-picture');
            Route::delete('models/{model}/delete-profile-picture', [ModelController::class, 'deleteProfilePicture'])->name('models.delete-profile-picture');
            Route::patch('models/{model}/status', [ModelController::class, 'updateStatus'])->name('models.update-status');
            Route::patch('models/{model}/events/{event}/casting-status', [ModelController::class, 'updateEventCastingStatus'])->name('models.update-event-casting-status');
            Route::post('models/{model}/toggle-top', [ModelController::class, 'toggleTop'])->name('models.toggle-top');
        });

        // Diseñadores - admin, operation, sales
        Route::middleware('section:designers')->group(function () {
            Route::get('designers/export', [DesignerController::class, 'exportDesigners'])->name('designers.export');
            Route::post('designers/import', [DesignerController::class, 'importDesigners'])->name('designers.import');
            Route::resource('designers', DesignerController::class);
            Route::patch('designers/{designer}/status', [DesignerController::class, 'updateStatus'])->name('designers.update-status');
            Route::post('designers/{designer}/assign-event', [DesignerController::class, 'assignEvent'])->name('designers.assign-event');
            Route::patch('designers/{designer}/cancel-event/{event}', [DesignerController::class, 'cancelEvent'])->name('designers.cancel-event');
            Route::delete('designers/{designer}/remove-event/{event}', [DesignerController::class, 'removeEvent'])->name('designers.remove-event');
            Route::post('designers/{designer}/assistants', [DesignerController::class, 'addAssistant'])->name('designers.add-assistant');
            Route::delete('designers/assistants/{assistant}', [DesignerController::class, 'removeAssistant'])->name('designers.remove-assistant');
            Route::patch('designers/{designer}/shows/{show}/cancel', [DesignerController::class, 'cancelShow'])->name('designers.cancel-show');
            Route::delete('designers/{designer}/shows/{show}', [DesignerController::class, 'removeShow'])->name('designers.remove-show');
            Route::post('designers/{designer}/shows', [DesignerController::class, 'addShow'])->name('designers.add-show');
            Route::put('designers/{designer}/fitting', [DesignerController::class, 'updateFitting'])->name('designers.update-fitting');
            Route::put('designer-materials/{material}', [DesignerController::class, 'updateMaterial'])->name('designers.update-material');
            Route::put('designer-displays/{display}', [DesignerController::class, 'updateDisplay'])->name('designers.update-display');
            Route::post('designer-displays/{display}/upload-video', [DesignerController::class, 'uploadVideo'])->name('designers.upload-video');
            Route::post('designer-displays/{display}/upload-audio', [DesignerController::class, 'uploadAudio'])->name('designers.upload-audio');
            Route::post('designers/{designer}/send-onboarding', [DesignerController::class, 'sendOnboardingEmail'])->name('designers.send-onboarding');
            Route::post('designers/send-bulk-onboarding', [DesignerController::class, 'sendBulkOnboardingEmail'])->name('designers.send-bulk-onboarding');
            Route::post('designers/{designer}/send-onboarding-sms', [DesignerController::class, 'sendOnboardingSms'])->name('designers.send-onboarding-sms');
            Route::post('designers/send-bulk-onboarding-sms', [DesignerController::class, 'sendBulkOnboardingSms'])->name('designers.send-bulk-onboarding-sms');
        });

        // Voluntarios - admin, operation
        Route::middleware('section:volunteers')->group(function () {
            Route::get('volunteers/export', [VolunteerController::class, 'exportVolunteers'])->name('volunteers.export');
            Route::post('volunteers/import', [VolunteerController::class, 'importVolunteers'])->name('volunteers.import');
            Route::resource('volunteers', VolunteerController::class)->only(['index', 'create', 'store', 'destroy']);
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

            // Fitting — asignación de diseñadores a slots
            Route::post('fitting-slots/{fittingSlot}/assign-designer', [EventController::class, 'assignDesignerToFitting'])->name('fitting-slots.assign-designer');
            Route::delete('fitting-slots/{fittingSlot}/remove-designer/{designer}', [EventController::class, 'removeDesignerFromFitting'])->name('fitting-slots.remove-designer');
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

        // Contabilidad - admin, accounting
        Route::prefix('accounting')->name('accounting.')->group(function () {
            Route::middleware('section:accounting_dashboard')->group(function () {
                Route::get('dashboard', [AccountingController::class, 'dashboard'])->name('dashboard');
            });
            Route::middleware('section:accounting_payments')->group(function () {
                Route::get('overdue', [AccountingController::class, 'overdueList'])->name('overdue');
                Route::get('overdue/export', [AccountingController::class, 'exportOverdueList'])->name('overdue.export');
                Route::get('designers-list', [AccountingController::class, 'designersList'])->name('designers-list');
                Route::get('api/designer-detail/{designer}', [AccountingController::class, 'designerDetail'])->name('api.designer-detail');
                Route::get('designers-list/export', [AccountingController::class, 'exportDesignersList'])->name('designers-list.export');

                Route::get('payments', [AccountingController::class, 'payments'])->name('payments');
                Route::get('payments/designer/{designer}/event/{event}', [AccountingController::class, 'showDesignerPayment'])->name('payments.designer');
                Route::post('payments/create-plan', [AccountingController::class, 'createPaymentPlan'])->name('payments.create-plan');
                Route::put('payments/plans/{plan}', [AccountingController::class, 'updatePaymentPlan'])->name('payments.update-plan');
                Route::post('payments/plans/{plan}/downpayment-paid', [AccountingController::class, 'markDownpaymentPaid'])->name('payments.downpayment-paid');
                Route::post('payments/installments/{installment}/mark-paid', [AccountingController::class, 'markInstallmentPaid'])->name('payments.installment-paid');
                Route::post('payments/installments/{installment}/upload-receipt', [AccountingController::class, 'uploadReceipt'])->name('payments.upload-receipt');
                Route::put('payments/designer/{designer}/event/{event}', [AccountingController::class, 'updateDesignerInfo'])->name('payments.update-designer');
                Route::get('api/designers-by-event/{event}', [AccountingController::class, 'designersByEvent'])->name('api.designers-by-event');
                Route::get('api/designers-all-events', [AccountingController::class, 'designersAllEvents'])->name('api.designers-all-events');

                // Registro de Pagos
                Route::get('payment-records', [AccountingController::class, 'paymentRecords'])->name('payment-records.index');
                Route::post('payment-records', [AccountingController::class, 'storePaymentRecord'])->name('payment-records.store');
                Route::put('payment-records/{record}', [AccountingController::class, 'updatePaymentRecord'])->name('payment-records.update');
                Route::delete('payment-records/{record}', [AccountingController::class, 'destroyPaymentRecord'])->name('payment-records.destroy');
                Route::get('api/search-designers', [AccountingController::class, 'searchDesignersForRecord'])->name('api.search-designers');

                // Historial / Bitácora
                Route::get('cases', [AccountingController::class, 'caseHistory'])->name('cases.index');
                Route::get('cases/create', [AccountingController::class, 'createCase'])->name('cases.create');
                Route::post('cases', [AccountingController::class, 'storeCase'])->name('cases.store');
                Route::get('cases/{case}', [AccountingController::class, 'showCase'])->name('cases.show');
                Route::post('cases/{case}/messages', [AccountingController::class, 'addMessage'])->name('cases.add-message');
                Route::put('cases/{case}/status', [AccountingController::class, 'updateCaseStatus'])->name('cases.update-status');
                Route::delete('cases/{case}', [AccountingController::class, 'destroyCase'])->name('cases.destroy');
                Route::get('api/designer-emails/{designer}', [AccountingController::class, 'getDesignerEmails'])->name('api.designer-emails');

                // Reporte de Liquidez
                Route::get('liquidity', [AccountingController::class, 'liquidityReport'])->name('liquidity');
                Route::get('liquidity/export', [AccountingController::class, 'exportLiquidityReport'])->name('liquidity.export');
            });
        });

        // Pases - admin, tickets_manager
        Route::middleware('section:tickets_management')->group(function () {
            Route::get('passes', [PassController::class, 'index'])->name('passes.index');
            Route::post('passes', [PassController::class, 'store'])->name('passes.store');
            Route::put('passes/{pass}', [PassController::class, 'update'])->name('passes.update');
            Route::delete('passes/{pass}', [PassController::class, 'destroy'])->name('passes.destroy');
            Route::post('passes/{pass}/check-in', [PassController::class, 'checkIn'])->name('passes.check-in');
            Route::post('passes/{pass}/reactivate', [PassController::class, 'reactivate'])->name('passes.reactivate');
            Route::get('api/passes/search-users', [PassController::class, 'searchUsers'])->name('passes.search-users');
        });

        // Ventas - admin, sales
        Route::prefix('sales')->name('sales.')->group(function () {
            Route::middleware('section:sales_dashboard')->group(function () {
                Route::get('dashboard', [SalesController::class, 'dashboard'])->name('dashboard');
                Route::get('history', [SalesController::class, 'history'])->name('history');
                Route::get('history/export', [SalesController::class, 'historyExport'])->name('history.export');
            });
            Route::middleware('section:sales_designers')->group(function () {
                Route::get('designers', [SalesController::class, 'index'])->name('designers.index');
                Route::get('designers/create', [SalesController::class, 'create'])->name('designers.create');
                Route::post('designers', [SalesController::class, 'store'])->name('designers.store');
                Route::get('designers/{registration}', [SalesController::class, 'show'])->name('designers.show');
                Route::patch('designers/{registration}', [SalesController::class, 'update'])->name('designers.update');
                Route::post('designers/{registration}/documents', [SalesController::class, 'uploadDocument'])->name('designers.upload-document');
                Route::delete('documents/{document}', [SalesController::class, 'deleteDocument'])->name('documents.destroy');
            });
        });

        // API de notificaciones (polling)
        Route::get('api/notifications', function () {
            return response()->json(request()->user()->notifications()->limit(30)->latest()->get());
        })->name('api.notifications');
        Route::post('api/notifications/mark-read', function () {
            request()->user()->unreadNotifications->markAsRead();
            return response()->json(['ok' => true]);
        })->name('api.notifications.mark-read');

        // Logs de actividad - solo admin
        Route::middleware('section:activity_logs')->group(function () {
            Route::get('logs', [ActivityLogController::class, 'index'])->name('logs.index');
        });

        // Ajustes - solo admin
        Route::middleware('section:settings')->group(function () {
            Route::prefix('settings')->name('settings.')->group(function () {
                Route::get('designers', [DesignerSettingsController::class, 'index'])->name('designers');
            });
        });

        // Categorías de diseñadores - admin, operation
        Route::middleware('section:designer_categories')->group(function () {
            Route::get('settings/designer-categories', [DesignerSettingsController::class, 'categories'])->name('settings.categories');
            Route::post('settings/designer-categories', [DesignerSettingsController::class, 'storeCategory'])->name('settings.designer-categories.store');
            Route::put('settings/designer-categories/{category}', [DesignerSettingsController::class, 'updateCategory'])->name('settings.designer-categories.update');
            Route::delete('settings/designer-categories/{category}', [DesignerSettingsController::class, 'destroyCategory'])->name('settings.designer-categories.destroy');
        });

        // Paquetes de diseñadores - admin, accounting
        Route::middleware('section:designer_packages')->group(function () {
            Route::get('settings/designer-packages', [DesignerSettingsController::class, 'packages'])->name('settings.packages');
            Route::post('settings/designer-packages', [DesignerSettingsController::class, 'storePackage'])->name('settings.designer-packages.store');
            Route::put('settings/designer-packages/{package}', [DesignerSettingsController::class, 'updatePackage'])->name('settings.designer-packages.update');
            Route::delete('settings/designer-packages/{package}', [DesignerSettingsController::class, 'destroyPackage'])->name('settings.designer-packages.destroy');
        });
    });
});
