<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app runs behind a reverse proxy (Traefik/nginx) that terminates https. Trust its
        // X-Forwarded-* headers so asset()/url() generate https:// links instead of http://
        // (which the browser blocks as mixed content / the proxy 404s).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccountIsActive::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
