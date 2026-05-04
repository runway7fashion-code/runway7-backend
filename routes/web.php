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
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\VolunteerController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadTagController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\LeadAnalyticsController;
use App\Http\Controllers\Admin\SalesAuditController;
use App\Http\Controllers\Admin\HomeCardController;
use App\Http\Controllers\Admin\PaymentMethodConfigController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\IncomingLeadController;
use App\Http\Controllers\Admin\LeadEmailController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\MaterialController;
use App\Http\Controllers\Admin\MaterialInstructionsController;
use App\Http\Controllers\Admin\ArtworkController;
use App\Http\Controllers\Admin\Sponsorship\CompanyController as SponsorshipCompanyController;
use App\Http\Controllers\Admin\Sponsorship\CategoryController as SponsorshipCategoryController;
use App\Http\Controllers\Admin\Sponsorship\PackageController as SponsorshipPackageController;
use App\Http\Controllers\Admin\Sponsorship\PackageBenefitController as SponsorshipPackageBenefitController;
use App\Http\Controllers\Admin\Sponsorship\TagController as SponsorshipTagController;
use App\Http\Controllers\Admin\CalendarActivityController;
use App\Http\Controllers\Admin\Sponsorship\LeadController as SponsorshipLeadController;
use App\Http\Controllers\Admin\Sponsorship\ConversionController as SponsorshipConversionController;
use App\Http\Controllers\Admin\Sponsorship\SponsorController as SponsorshipSponsorController;
use App\Http\Controllers\Admin\Sponsorship\DashboardController as SponsorshipDashboardController;

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
            Route::resource('users', UserController::class)->except(['destroy']);
        });

        // ═══════════════════════════════════════════
        // OPERATIONS — /admin/operations/...
        // ═══════════════════════════════════════════
        Route::prefix('operations')->group(function () {

            // Modelos - admin, operation
            Route::middleware('section:models')->group(function () {
                Route::get('models/export', [ModelController::class, 'exportModels'])->name('models.export');
                Route::post('models/import', [ModelController::class, 'importModels'])->name('models.import');
                Route::get('models/import-template', [ModelController::class, 'downloadImportTemplate'])->name('models.import-template');
                Route::post('models/send-pending-emails', [ModelController::class, 'sendPendingWelcomeEmails'])->name('models.send-pending-emails');
                Route::resource('models', ModelController::class)->except(['destroy']);
                Route::post('models/{model}/assign-event', [ModelController::class, 'assignEvent'])->name('models.assign-event');
                Route::delete('models/{model}/remove-event/{event}', [ModelController::class, 'removeEvent'])->name('models.remove-event');
                Route::post('models/{model}/upload-photo/{position}', [ModelController::class, 'uploadPhoto'])->name('models.upload-photo');
                Route::delete('models/{model}/delete-photo/{position}', [ModelController::class, 'deletePhoto'])->name('models.delete-photo');
                Route::post('models/{model}/send-welcome-email', [ModelController::class, 'sendWelcomeEmail'])->name('models.send-welcome-email');
                Route::post('models/{model}/upload-profile-picture', [ModelController::class, 'uploadProfilePicture'])->name('models.upload-profile-picture');
                Route::delete('models/{model}/delete-profile-picture', [ModelController::class, 'deleteProfilePicture'])->name('models.delete-profile-picture');
                Route::patch('models/{model}/status', [ModelController::class, 'updateStatus'])->name('models.update-status');
                Route::patch('models/{model}/events/{event}/casting-status', [ModelController::class, 'updateEventCastingStatus'])->name('models.update-event-casting-status');
                Route::patch('models/{model}/events/{event}/model-tag', [ModelController::class, 'updateModelTag'])->name('models.update-model-tag');
                Route::post('models/{model}/events/{event}/send-onboarding', [ModelController::class, 'sendModelOnboarding'])->name('models.send-onboarding');
                Route::post('models/{model}/toggle-top', [ModelController::class, 'toggleTop'])->name('models.toggle-top');
                Route::post('models/{model}/send-onboarding-sms', [ModelController::class, 'sendOnboardingSms'])->name('models.send-onboarding-sms');
                Route::post('models/{model}/send-rejection-email', [ModelController::class, 'sendRejectionEmail'])->name('models.send-rejection-email');
                Route::post('models/send-bulk-rejection-emails', [ModelController::class, 'sendBulkRejectionEmails'])->name('models.send-bulk-rejection-emails');
                Route::post('models/send-bulk-onboarding-sms', [ModelController::class, 'sendBulkOnboardingSms'])->name('models.send-bulk-onboarding-sms');
                Route::post('models/send-bulk-rejection-sms', [ModelController::class, 'sendBulkRejectionSms'])->name('models.send-bulk-rejection-sms');
            });

            // Diseñadores - admin, operation, sales
            Route::middleware('section:designers')->group(function () {
                Route::get('designers/export', [DesignerController::class, 'exportDesigners'])->name('designers.export');
                Route::post('designers/import', [DesignerController::class, 'importDesigners'])->name('designers.import');
                Route::get('designers/overdue-materials', [DesignerController::class, 'overdueMaterials'])->name('designers.overdue-materials');
                Route::post('designers/{designer}/events/{event}/send-deadline-reminder', [DesignerController::class, 'sendDeadlineReminder'])->name('designers.send-deadline-reminder');
                Route::get('designers/material-instructions', [MaterialInstructionsController::class, 'index'])->name('designers.material-instructions.index');
                Route::patch('designers/material-instructions/{instruction}', [MaterialInstructionsController::class, 'update'])->name('designers.material-instructions.update');
                Route::patch('designers/material-instructions/events/{event}/deadline', [MaterialInstructionsController::class, 'updateEventDeadline'])->name('designers.material-instructions.update-event-deadline');
                Route::post('designers/material-instructions/events/{event}/shared/upload-url', [MaterialInstructionsController::class, 'sharedUploadUrl'])->name('designers.material-instructions.shared.upload-url');
                Route::post('designers/material-instructions/events/{event}/shared/upload-complete', [MaterialInstructionsController::class, 'sharedUploadComplete'])->name('designers.material-instructions.shared.upload-complete');
                Route::delete('designers/material-instructions/shared/{eventSharedMaterial}', [MaterialInstructionsController::class, 'sharedDestroy'])->name('designers.material-instructions.shared.destroy');
                Route::resource('designers', DesignerController::class)->except(['destroy']);
                Route::patch('designers/{designer}/status', [DesignerController::class, 'updateStatus'])->name('designers.update-status');
                Route::post('designers/{designer}/assign-event', [DesignerController::class, 'assignEvent'])->name('designers.assign-event');
                Route::post('designers/{designer}/events/{event}/toggle-feature', [DesignerController::class, 'toggleEventFeature'])->name('designers.toggle-event-feature');
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
                Route::post('designers/{designer}/upload-profile-picture', [DesignerController::class, 'uploadProfilePicture'])->name('designers.upload-profile-picture');

                // Materials
                Route::get('designers/{designer}/materials/{eventId}', [MaterialController::class, 'show'])->name('designers.materials');
                Route::patch('materials/{material}/status', [MaterialController::class, 'updateStatus'])->name('materials.update-status');
                Route::post('materials/{material}/upload-url', [MaterialController::class, 'generateUploadUrl'])->name('materials.upload-url');
                Route::post('materials/{material}/confirm-upload', [MaterialController::class, 'confirmUpload'])->name('materials.confirm-upload');
                Route::delete('material-files/{file}', [MaterialController::class, 'deleteFile'])->name('materials.delete-file');
                Route::put('materials/{material}/bio', [MaterialController::class, 'saveBio'])->name('materials.save-bio');
                Route::post('materials/{material}/moodboard-image', [MaterialController::class, 'uploadMoodboardImage'])->name('materials.upload-moodboard');
                Route::patch('moodboard-items/{item}/respond', [MaterialController::class, 'respondMoodboard'])->name('materials.respond-moodboard');
                Route::post('materials/{material}/observe', [MaterialController::class, 'observe'])->name('materials.observe');
                Route::patch('designers/{designer}/materials-deadline/{eventId}', [MaterialController::class, 'updateDeadline'])->name('materials.update-deadline');
                Route::post('materials/runway-logo/{eventId}', [MaterialController::class, 'uploadRunwayLogo'])->name('materials.upload-runway-logo');
                Route::delete('designers/{designer}/delete-profile-picture', [DesignerController::class, 'deleteProfilePicture'])->name('designers.delete-profile-picture');
                Route::post('designers/{designer}/send-onboarding', [DesignerController::class, 'sendOnboardingEmail'])->name('designers.send-onboarding');
                Route::post('designers/send-bulk-onboarding', [DesignerController::class, 'sendBulkOnboardingEmail'])->name('designers.send-bulk-onboarding');
                Route::post('designers/{designer}/send-onboarding-sms', [DesignerController::class, 'sendOnboardingSms'])->name('designers.send-onboarding-sms');
                Route::post('designers/send-bulk-onboarding-sms', [DesignerController::class, 'sendBulkOnboardingSms'])->name('designers.send-bulk-onboarding-sms');
            });

            // Asistencia - admin, operation
            Route::middleware('section:attendance')->group(function () {
                Route::get('attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
                Route::get('attendance/user-search', [AttendanceController::class, 'userSearch'])->name('attendance.user-search');
                Route::get('attendance/user-events', [AttendanceController::class, 'userEvents'])->name('attendance.user-events');
                Route::get('attendance/event-days/{event}', [AttendanceController::class, 'eventDays'])->name('attendance.event-days');
                Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
                Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
                Route::put('attendance/{checkin}', [AttendanceController::class, 'update'])->name('attendance.update');
                Route::delete('attendance/{checkin}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');
            });

            // Voluntarios - admin, operation
            Route::middleware('section:volunteers')->group(function () {
                Route::get('volunteers/export', [VolunteerController::class, 'exportVolunteers'])->name('volunteers.export');
                Route::get('volunteers/import-template', [VolunteerController::class, 'downloadImportTemplate'])->name('volunteers.import-template');
                Route::post('volunteers/import', [VolunteerController::class, 'importVolunteers'])->name('volunteers.import');
                Route::post('volunteers/send-bulk-onboarding', [VolunteerController::class, 'sendBulkOnboardingEmails'])->name('volunteers.send-bulk-onboarding');
                Route::post('volunteers/send-bulk-onboarding-sms', [VolunteerController::class, 'sendBulkOnboardingSms'])->name('volunteers.send-bulk-onboarding-sms');
                Route::resource('volunteers', VolunteerController::class)->except(['destroy']);
                Route::patch('volunteers/{volunteer}/status', [VolunteerController::class, 'updateStatus'])->name('volunteers.update-status');
                Route::post('volunteers/{volunteer}/assign-event', [VolunteerController::class, 'assignEvent'])->name('volunteers.assign-event');
                Route::delete('volunteers/{volunteer}/remove-event/{event}', [VolunteerController::class, 'removeEvent'])->name('volunteers.remove-event');
                Route::patch('volunteers/{volunteer}/events/{event}/area', [VolunteerController::class, 'updateEventArea'])->name('volunteers.update-event-area');
                Route::patch('volunteers/{volunteer}/events/{event}/status', [VolunteerController::class, 'updateEventStatus'])->name('volunteers.update-event-status');
                Route::post('volunteers/{volunteer}/schedules', [VolunteerController::class, 'addSchedule'])->name('volunteers.add-schedule');
                Route::delete('volunteers/{volunteer}/schedules/{schedule}', [VolunteerController::class, 'removeSchedule'])->name('volunteers.remove-schedule');
                Route::post('volunteers/{volunteer}/send-onboarding', [VolunteerController::class, 'sendOnboardingEmail'])->name('volunteers.send-onboarding');
                Route::post('volunteers/{volunteer}/send-onboarding-sms', [VolunteerController::class, 'sendOnboardingSms'])->name('volunteers.send-onboarding-sms');
                Route::get('volunteers/{volunteer}/certificate/{event}', [VolunteerController::class, 'certificate'])->name('volunteers.certificate');
            });

            // Media - admin, operation
            Route::middleware('section:media')->group(function () {
                Route::resource('media', MediaController::class)->parameters(['media' => 'media'])->except(['destroy']);
                Route::patch('media/{media}/status', [MediaController::class, 'updateStatus'])->name('media.update-status');
                Route::post('media/{media}/assign-event', [MediaController::class, 'assignEvent'])->name('media.assign-event');
                Route::delete('media/{media}/remove-event/{event}', [MediaController::class, 'removeEvent'])->name('media.remove-event');
                Route::patch('media/{media}/events/{event}/status', [MediaController::class, 'updateEventStatus'])->name('media.update-event-status');
                Route::post('media/{media}/send-onboarding', [MediaController::class, 'sendOnboardingEmail'])->name('media.send-onboarding');
                Route::post('media/{media}/send-onboarding-sms', [MediaController::class, 'sendOnboardingSms'])->name('media.send-onboarding-sms');
                Route::post('media/send-bulk-onboarding', [MediaController::class, 'sendBulkOnboardingEmails'])->name('media.send-bulk-onboarding');
                Route::post('media/send-bulk-onboarding-sms', [MediaController::class, 'sendBulkOnboardingSms'])->name('media.send-bulk-onboarding-sms');
                Route::post('media/{media}/assistants', [MediaController::class, 'storeAssistant'])->name('media.store-assistant');
                Route::delete('media/{media}/assistants/{assistant}', [MediaController::class, 'destroyAssistant'])->name('media.destroy-assistant');
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
                Route::post('chats', [ChatController::class, 'store'])->name('chats.store');
                Route::post('chats/groups', [ChatController::class, 'createGroup'])->name('chats.groups.store');
                Route::get('chats/support-assignments', [ChatController::class, 'supportAssignments'])->name('chats.support-assignments');
                Route::put('chats/support-assignments', [ChatController::class, 'saveSupportAssignments'])->name('chats.support-assignments.save');
                Route::get('chats/{conversation}', [ChatController::class, 'show'])->name('chats.show');
                Route::post('chats/{conversation}/messages', [ChatController::class, 'sendMessage'])->name('chats.send-message');
                Route::post('chats/{conversation}/reassign', [ChatController::class, 'reassign'])->name('chats.reassign');
            });

            // Categorías de diseñadores - admin, operation
            Route::middleware('section:designer_categories')->group(function () {
                Route::get('categories', [DesignerSettingsController::class, 'categories'])->name('settings.categories');
                Route::post('categories', [DesignerSettingsController::class, 'storeCategory'])->name('settings.designer-categories.store');
                Route::put('categories/{category}', [DesignerSettingsController::class, 'updateCategory'])->name('settings.designer-categories.update');
                Route::delete('categories/{category}', [DesignerSettingsController::class, 'destroyCategory'])->name('settings.designer-categories.destroy');
            });

            // Países y phone codes - admin, operation
            Route::middleware('section:countries')->group(function () {
                Route::get('countries', [CountryController::class, 'index'])->name('settings.countries');
                Route::post('countries', [CountryController::class, 'store'])->name('settings.countries.store');
                Route::put('countries/{country}', [CountryController::class, 'update'])->name('settings.countries.update');
                Route::delete('countries/{country}', [CountryController::class, 'destroy'])->name('settings.countries.destroy');
            });

            // Incoming leads from sales - admin, operation
            Route::middleware('section:incoming_leads')->group(function () {
                Route::get('incoming-leads', [IncomingLeadController::class, 'index'])->name('incoming-leads.index');
                Route::post('incoming-leads/{lead}/convert', [IncomingLeadController::class, 'convert'])->name('incoming-leads.convert');
                Route::patch('incoming-leads/{lead}/reject', [IncomingLeadController::class, 'reject'])->name('incoming-leads.reject');
            });

            // Banners - admin, marketing
            Route::middleware('section:banners')->group(function () {
                Route::resource('banners', BannerController::class);
                Route::post('banners/{banner}/upload-image', [BannerController::class, 'uploadImage'])->name('banners.upload-image');
                Route::post('banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');

                // Home Cards (same access as banners)
                Route::resource('home-cards', HomeCardController::class);
                Route::post('home-cards/{home_card}/upload-image', [HomeCardController::class, 'uploadImage'])->name('home-cards.upload-image');
                Route::post('home-cards/reorder', [HomeCardController::class, 'reorder'])->name('home-cards.reorder');
            });

        }); // end operations

        // Communications - admin, operation, sales, marketing, public_relations, tickets_manager, accounting
        Route::prefix('communications')->name('communications.')->group(function () {
            Route::middleware('section:communications')->group(function () {
                Route::get('email', [CommunicationController::class, 'email'])->name('email');
                Route::post('email/send', [CommunicationController::class, 'sendEmail'])->name('email.send');
                Route::get('sms', [CommunicationController::class, 'sms'])->name('sms');
                Route::post('sms/preview', [CommunicationController::class, 'previewSms'])->name('sms.preview');
                Route::post('sms/send', [CommunicationController::class, 'sendSms'])->name('sms.send');
                Route::get('notifications', [CommunicationController::class, 'notifications'])->name('notifications');
                Route::post('notifications/preview', [CommunicationController::class, 'previewNotifications'])->name('notifications.preview');
                Route::post('notifications/send', [CommunicationController::class, 'sendNotifications'])->name('notifications.send');
            });
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

                // Payment Methods Config
                Route::get('payment-methods', [PaymentMethodConfigController::class, 'index'])->name('payment-methods.index');
                Route::post('payment-methods', [PaymentMethodConfigController::class, 'store'])->name('payment-methods.store');
                Route::put('payment-methods/{paymentMethodConfig}', [PaymentMethodConfigController::class, 'update'])->name('payment-methods.update');
                Route::delete('payment-methods/{paymentMethodConfig}', [PaymentMethodConfigController::class, 'destroy'])->name('payment-methods.destroy');
                Route::post('payment-methods/reorder', [PaymentMethodConfigController::class, 'reorder'])->name('payment-methods.reorder');
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

        // Tickets - Artworks management
        Route::prefix('tickets')->name('tickets.')->group(function () {
            Route::middleware('section:tickets_management')->group(function () {
                Route::get('artworks', [ArtworkController::class, 'index'])->name('artworks.index');
                Route::get('artworks/{designer}/{eventId}', [ArtworkController::class, 'show'])->name('artworks.show');
                Route::post('artworks/{material}/upload-url', [ArtworkController::class, 'generateUploadUrl'])->name('artworks.upload-url');
                Route::post('artworks/{material}/confirm-upload', [ArtworkController::class, 'confirmUpload'])->name('artworks.confirm-upload');
                Route::delete('artwork-files/{file}', [ArtworkController::class, 'deleteFile'])->name('artworks.delete-file');
            });
        });

        // Ventas - admin, sales
        Route::prefix('sales')->name('sales.')->middleware('sales.audit')->group(function () {
            Route::middleware('section:sales_dashboard')->group(function () {
                Route::get('dashboard', [SalesController::class, 'dashboard'])->name('dashboard');
                Route::get('history', [SalesController::class, 'history'])->name('history');
                Route::get('history/export', [SalesController::class, 'historyExport'])->name('history.export');
            });
            Route::middleware('section:sales_designers')->group(function () {
                Route::get('designers', [SalesController::class, 'index'])->name('designers.index');
                Route::get('designers/export', [SalesController::class, 'exportDesigners'])->name('designers.export');
                Route::get('designers/create', [SalesController::class, 'create'])->name('designers.create');
                Route::post('designers', [SalesController::class, 'store'])->name('designers.store');
                Route::get('designers/{registration}', [SalesController::class, 'show'])->name('designers.show');
                Route::patch('designers/{registration}', [SalesController::class, 'update'])->name('designers.update');
                Route::delete('designers/{registration}/undo', [SalesController::class, 'undoConversion'])->name('designers.undo');
                Route::post('designers/{registration}/documents', [SalesController::class, 'uploadDocument'])->name('designers.upload-document');
                Route::delete('documents/{document}', [SalesController::class, 'deleteDocument'])->name('documents.destroy');
            });
            Route::middleware('section:sales_leads')->group(function () {
                Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
                Route::get('leads/export', [LeadController::class, 'export'])->name('leads.export');
                Route::get('leads/import-template', [LeadController::class, 'downloadImportTemplate'])->name('leads.import-template');
                Route::post('leads/import', [LeadController::class, 'importLeads'])->name('leads.import');
                Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
                Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
                Route::get('leads/search', [LeadController::class, 'search'])->name('leads.search');
                Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
                Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
                Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
                Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.update-status');
                Route::patch('leads/{lead}/event-status', [LeadController::class, 'updateEventStatus'])->name('leads.update-event-status');
                Route::patch('leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');
                Route::patch('leads/{lead}/redirect', [LeadController::class, 'redirectToOperations'])->name('leads.redirect');
                Route::post('leads/{lead}/send-email', [LeadEmailController::class, 'send'])->name('leads.send-email');
                Route::post('leads/send-bulk-email', [LeadEmailController::class, 'sendBulk'])->name('leads.send-bulk-email');
                Route::post('leads/{lead}/activity', [LeadController::class, 'addActivity'])->name('leads.add-activity');
                Route::patch('activities/{activity}/complete', [LeadController::class, 'completeActivity'])->name('leads.complete-activity');
                Route::patch('activities/{activity}/cancel', [LeadController::class, 'cancelActivity'])->name('leads.cancel-activity');
                Route::patch('activities/{activity}/not-completed', [LeadController::class, 'notCompletedActivity'])->name('leads.not-completed-activity');
                Route::patch('leads/{lead}/tags', [LeadController::class, 'syncTags'])->name('leads.sync-tags');
                Route::post('leads/{lead}/add-event', [LeadController::class, 'addEvent'])->name('leads.add-event');
                Route::delete('leads/{lead}/remove-event', [LeadController::class, 'removeEvent'])->name('leads.remove-event');
                Route::post('toggle-availability', [LeadController::class, 'toggleAvailability'])->name('toggle-availability');
                // Tags CRUD
                Route::get('tags', [LeadTagController::class, 'index'])->name('tags.index');
                Route::post('tags', [LeadTagController::class, 'store'])->name('tags.store');
                Route::put('tags/{tag}', [LeadTagController::class, 'update'])->name('tags.update');
                Route::delete('tags/{tag}', [LeadTagController::class, 'destroy'])->name('tags.destroy');
            });
            Route::middleware('section:sales_calendar')->group(function () {
                Route::get('calendar', [LeadController::class, 'calendar'])->name('calendar');
                Route::get('calendar/events', [LeadController::class, 'calendarEvents'])->name('calendar.events');

                // Personal calendar entries (mirror of sponsorship)
                Route::post('calendar-activities',                            [CalendarActivityController::class, 'store'])->name('calendar-activities.store');
                Route::match(['put','patch'], 'calendar-activities/{calendar_activity}', [CalendarActivityController::class, 'update'])->name('calendar-activities.update');
                Route::patch('calendar-activities/{calendar_activity}/complete',      [CalendarActivityController::class, 'complete'])->name('calendar-activities.complete');
                Route::patch('calendar-activities/{calendar_activity}/cancel',        [CalendarActivityController::class, 'cancel'])->name('calendar-activities.cancel');
                Route::patch('calendar-activities/{calendar_activity}/not-completed', [CalendarActivityController::class, 'notCompleted'])->name('calendar-activities.not-completed');
                Route::patch('calendar-activities/{calendar_activity}/pending',       [CalendarActivityController::class, 'markPending'])->name('calendar-activities.pending');
                Route::delete('calendar-activities/{calendar_activity}',              [CalendarActivityController::class, 'destroy'])->name('calendar-activities.destroy');

                Route::get('calendar/availability', fn(\Illuminate\Http\Request $r) => app(CalendarActivityController::class)->availability($r, 'sales'))->name('calendar.availability');
            });
            // Analytics - admin, sales lider
            Route::middleware('section:sales_leads')->group(function () {
                Route::get('analytics', [LeadAnalyticsController::class, 'index'])->name('analytics');
                Route::get('analytics/export', [LeadAnalyticsController::class, 'export'])->name('analytics.export');
            });
            // Sales Audit Logs - admin, sales lider
            Route::middleware('section:sales_leads')->group(function () {
                Route::get('logs', [SalesAuditController::class, 'index'])->name('logs');
            });
            // Bot messages API (all sales users)
            Route::get('bot/messages', [LeadController::class, 'botMessages'])->name('bot.messages');
            Route::post('bot/ask', [LeadController::class, 'botAsk'])->name('bot.ask');
            Route::post('bot/mark-read', [LeadController::class, 'botMarkRead'])->name('bot.mark-read');
            Route::post('bot/mark-all-read', [LeadController::class, 'botMarkAllRead'])->name('bot.mark-all-read');
            // Paquetes de diseñadores - admin, sales lider
            Route::middleware('section:designer_packages')->group(function () {
                Route::get('packages', [DesignerSettingsController::class, 'packages'])->name('packages');
                Route::post('packages', [DesignerSettingsController::class, 'storePackage'])->name('packages.store');
                Route::put('packages/{package}', [DesignerSettingsController::class, 'updatePackage'])->name('packages.update');
                Route::delete('packages/{package}', [DesignerSettingsController::class, 'destroyPackage'])->name('packages.destroy');
            });
        });

        // Sponsorship — admin, sponsorship (lider/asesor)
        Route::prefix('sponsorship')->name('sponsorship.')->group(function () {
            // Dashboard
            Route::middleware('section:sponsorship_dashboard')->group(function () {
                Route::get('dashboard', [SponsorshipDashboardController::class, 'index'])->name('dashboard');
            });

            // Companies
            Route::middleware('section:sponsorship_companies')->group(function () {
                Route::get('companies', [SponsorshipCompanyController::class, 'index'])->name('companies.index');
                Route::get('companies/search', [SponsorshipCompanyController::class, 'search'])->name('companies.search');
                Route::post('companies', [SponsorshipCompanyController::class, 'store'])->name('companies.store');
                Route::get('companies/{company}/edit', [SponsorshipCompanyController::class, 'edit'])->name('companies.edit');
                Route::put('companies/{company}', [SponsorshipCompanyController::class, 'update'])->name('companies.update');
                Route::delete('companies/{company}', [SponsorshipCompanyController::class, 'destroy'])->name('companies.destroy');
            });

            // Categories
            Route::middleware('section:sponsorship_categories')->group(function () {
                Route::get('categories', [SponsorshipCategoryController::class, 'index'])->name('categories.index');
                Route::post('categories', [SponsorshipCategoryController::class, 'store'])->name('categories.store');
                Route::put('categories/{category}', [SponsorshipCategoryController::class, 'update'])->name('categories.update');
                Route::delete('categories/{category}', [SponsorshipCategoryController::class, 'destroy'])->name('categories.destroy');
            });

            // Packages
            Route::middleware('section:sponsorship_packages')->group(function () {
                Route::get('packages', [SponsorshipPackageController::class, 'index'])->name('packages.index');
                Route::get('packages/create', [SponsorshipPackageController::class, 'create'])->name('packages.create');
                Route::post('packages', [SponsorshipPackageController::class, 'store'])->name('packages.store');
                Route::get('packages/{package}/edit', [SponsorshipPackageController::class, 'edit'])->name('packages.edit');
                Route::put('packages/{package}', [SponsorshipPackageController::class, 'update'])->name('packages.update');
                Route::delete('packages/{package}', [SponsorshipPackageController::class, 'destroy'])->name('packages.destroy');
            });

            // Benefits (solo lider/admin)
            Route::middleware('section:sponsorship_benefits')->group(function () {
                Route::get('benefits', [SponsorshipPackageBenefitController::class, 'index'])->name('benefits.index');
                Route::post('benefits', [SponsorshipPackageBenefitController::class, 'store'])->name('benefits.store');
                Route::put('benefits/{benefit}', [SponsorshipPackageBenefitController::class, 'update'])->name('benefits.update');
                Route::delete('benefits/{benefit}', [SponsorshipPackageBenefitController::class, 'destroy'])->name('benefits.destroy');
            });

            // Tags
            Route::middleware('section:sponsorship_tags')->group(function () {
                Route::get('tags', [SponsorshipTagController::class, 'index'])->name('tags.index');
                Route::post('tags', [SponsorshipTagController::class, 'store'])->name('tags.store');
                Route::put('tags/{tag}', [SponsorshipTagController::class, 'update'])->name('tags.update');
                Route::delete('tags/{tag}', [SponsorshipTagController::class, 'destroy'])->name('tags.destroy');
            });

            // Leads
            Route::middleware('section:sponsorship_leads')->group(function () {
                Route::get('leads', [SponsorshipLeadController::class, 'index'])->name('leads.index');
                Route::get('leads/create', [SponsorshipLeadController::class, 'create'])->name('leads.create');
                Route::post('leads', [SponsorshipLeadController::class, 'store'])->name('leads.store');
                Route::get('leads/{lead}', [SponsorshipLeadController::class, 'show'])->name('leads.show');
                Route::get('leads/{lead}/edit', [SponsorshipLeadController::class, 'edit'])->name('leads.edit');
                Route::put('leads/{lead}', [SponsorshipLeadController::class, 'update'])->name('leads.update');

                Route::patch('leads/{lead}/status', [SponsorshipLeadController::class, 'updateStatus'])->name('leads.update-status');
                Route::patch('leads/{lead}/assign', [SponsorshipLeadController::class, 'assign'])->name('leads.assign');
                Route::patch('leads/{lead}/tags', [SponsorshipLeadController::class, 'syncTags'])->name('leads.sync-tags');
                Route::post('leads/{lead}/add-event', [SponsorshipLeadController::class, 'addEvent'])->name('leads.add-event');
                Route::delete('leads/{lead}/remove-event', [SponsorshipLeadController::class, 'removeEvent'])->name('leads.remove-event');

                Route::post('leads/{lead}/send-email', [SponsorshipLeadController::class, 'sendEmail'])->name('leads.send-email');
                Route::post('leads/bulk-send-email',   [SponsorshipLeadController::class, 'bulkSendEmail'])->name('leads.bulk-send-email');

                // Conversion lead → sponsor
                Route::get('leads/{lead}/convert', [SponsorshipConversionController::class, 'show'])->name('leads.convert.show');
                Route::post('leads/{lead}/convert', [SponsorshipConversionController::class, 'store'])->name('leads.convert.store');

                // Activities / Timeline
                Route::post('leads/{lead}/activities', [SponsorshipLeadController::class, 'addActivity'])->name('leads.add-activity');
                Route::patch('activities/{activity}/complete', [SponsorshipLeadController::class, 'completeActivity'])->name('activities.complete');
                Route::patch('activities/{activity}/cancel', [SponsorshipLeadController::class, 'cancelActivity'])->name('activities.cancel');
                Route::patch('activities/{activity}/not-completed', [SponsorshipLeadController::class, 'notCompletedActivity'])->name('activities.not-completed');
                Route::patch('activities/{activity}/pending', [SponsorshipLeadController::class, 'markPendingActivity'])->name('activities.pending');
                Route::match(['put', 'patch'], 'activities/{activity}', [SponsorshipLeadController::class, 'updateActivity'])->name('activities.update');
                Route::delete('activities/{activity}', [SponsorshipLeadController::class, 'destroyActivity'])->name('activities.destroy');
            });

            // Calendar
            Route::middleware('section:sponsorship_calendar')->group(function () {
                Route::get('calendar', [SponsorshipLeadController::class, 'calendar'])->name('calendar');
                Route::get('calendar/events', [SponsorshipLeadController::class, 'calendarEvents'])->name('calendar.events');

                // Personal calendar entries (not tied to a lead). Same controller serves sales too.
                Route::post('calendar-activities',                            [CalendarActivityController::class, 'store'])->name('calendar-activities.store');
                Route::match(['put','patch'], 'calendar-activities/{calendar_activity}', [CalendarActivityController::class, 'update'])->name('calendar-activities.update');
                Route::patch('calendar-activities/{calendar_activity}/complete',      [CalendarActivityController::class, 'complete'])->name('calendar-activities.complete');
                Route::patch('calendar-activities/{calendar_activity}/cancel',        [CalendarActivityController::class, 'cancel'])->name('calendar-activities.cancel');
                Route::patch('calendar-activities/{calendar_activity}/not-completed', [CalendarActivityController::class, 'notCompleted'])->name('calendar-activities.not-completed');
                Route::patch('calendar-activities/{calendar_activity}/pending',       [CalendarActivityController::class, 'markPending'])->name('calendar-activities.pending');
                Route::delete('calendar-activities/{calendar_activity}',              [CalendarActivityController::class, 'destroy'])->name('calendar-activities.destroy');

                Route::get('calendar/availability', fn(\Illuminate\Http\Request $r) => app(CalendarActivityController::class)->availability($r, 'sponsorship'))->name('calendar.availability');
            });

            // Sponsors
            Route::middleware('section:sponsorship_sponsors')->group(function () {
                Route::get('sponsors', [SponsorshipSponsorController::class, 'index'])->name('sponsors.index');
                Route::get('sponsors/{user}', [SponsorshipSponsorController::class, 'show'])->name('sponsors.show');
                Route::post('sponsors/{user}/send-onboarding', [SponsorshipSponsorController::class, 'sendOnboarding'])->name('sponsors.send-onboarding');
                Route::post('sponsors/{user}/guests', [SponsorshipSponsorController::class, 'addGuest'])->name('sponsors.add-guest');
                Route::delete('guests/{guest}', [SponsorshipSponsorController::class, 'removeGuest'])->name('sponsors.remove-guest');
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

        // Help / Knowledge Base - all internal users
        Route::get('help', [HelpController::class, 'index'])->name('help.index');
        Route::get('help/create', [HelpController::class, 'create'])->name('help.create');
        Route::post('help', [HelpController::class, 'store'])->name('help.store');
        Route::get('help/{article}', [HelpController::class, 'show'])->name('help.show');
        Route::get('help/{article}/edit', [HelpController::class, 'edit'])->name('help.edit');
        Route::put('help/{article}', [HelpController::class, 'update'])->name('help.update');
        Route::delete('help/{article}', [HelpController::class, 'destroy'])->name('help.destroy');
        Route::delete('help-attachments/{attachment}', [HelpController::class, 'deleteAttachment'])->name('help.delete-attachment');

        // Ajustes - solo admin
        Route::middleware('section:settings')->group(function () {
            Route::prefix('settings')->name('settings.')->group(function () {
                Route::get('designers', [DesignerSettingsController::class, 'index'])->name('designers');
            });
        });
    });
});
