<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckSectionAccess;
use App\Http\Middleware\EnsureUserIsInternal;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogSalesAction;
use App\Http\Middleware\TrackUserPresence;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            TrackUserPresence::class,
        ]);
        $middleware->api(append: [
            TrackUserPresence::class,
        ]);
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->alias([
            'internal' => EnsureUserIsInternal::class,
            'section' => CheckSectionAccess::class,
            'sales.audit' => LogSalesAction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
