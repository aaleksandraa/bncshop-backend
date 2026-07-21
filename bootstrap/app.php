<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');

        if (filled($trustedProxies)) {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', (string) $trustedProxies)),
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->api(prepend: [
            \App\Http\Middleware\EnsureFreshConnections::class,
            EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\AddPublicApiCacheHeaders::class,
        ]);

        $middleware->web(prepend: [
            \App\Http\Middleware\EnsureFreshConnections::class,
        ]);

        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'seller' => \App\Http\Middleware\EnsureSeller::class,
            'auth.optional' => \App\Http\Middleware\AuthenticateOptionalSanctum::class,
            'partner.export.secure' => \App\Http\Middleware\SecurePartnerExport::class,
            'partner.export' => \App\Http\Middleware\AuthenticatePartnerExport::class,
            'partner.export.headers' => \App\Http\Middleware\AddPartnerExportResponseHeaders::class,
            'b2b.customer' => \App\Http\Middleware\EnsureB2bCustomer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
