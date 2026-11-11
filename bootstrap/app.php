<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustRenderClientIp;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\EnsureUserCanRecordDistributions;
use App\Http\Middleware\EnsureUserCanReleasePackages;
use App\Http\Middleware\EnsureSuperAdminHasWorkspaceForWrites;
use App\Http\Middleware\EnsureUserCanUseMobileApi;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureSuperAdminMfaVerified;
use App\Console\Commands\ExpireOverdueDistributions;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ExpireOverdueDistributions::class,
        \App\Console\Commands\PromoteUserToSuperAdmin::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxyHeaders = Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT;
        if (env('TRUST_RENDER_CLIENT_IP', false)) {
            $trustedProxyHeaders |= Request::HEADER_X_FORWARDED_FOR;
        }

        // Trust only the immediate peer for HTTPS metadata. Client IPs are
        // canonicalized from Render's edge header when explicitly enabled.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', 'REMOTE_ADDR'),
            headers: $trustedProxyHeaders,
        );
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'super-admin' => EnsureUserIsSuperAdmin::class,
            'distribution-operator' => EnsureUserCanRecordDistributions::class,
            'distribution-staff' => EnsureUserCanReleasePackages::class,
            'barangay-write-context' => EnsureSuperAdminHasWorkspaceForWrites::class,
            'mobile-user' => EnsureUserCanUseMobileApi::class,
            'account-active' => EnsureUserIsActive::class,
            'super-admin-mfa' => EnsureSuperAdminMfaVerified::class,
        ]);
        $middleware->web(append: [
            TrustRenderClientIp::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);
        $middleware->api(append: [TrustRenderClientIp::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
