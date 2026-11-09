<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserCanRecordDistributions;
use App\Http\Middleware\EnsureUserCanReleasePackages;
use App\Console\Commands\ExpireOverdueDistributions;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([ExpireOverdueDistributions::class])
    ->withMiddleware(function (Middleware $middleware): void {
        // Render's proxy addresses can change; trust only the immediate proxy hop
        // and use its forwarded headers to resolve the original client IP.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'distribution-operator' => EnsureUserCanRecordDistributions::class,
            'distribution-staff' => EnsureUserCanReleasePackages::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
