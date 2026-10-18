<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\AdminOrEmpleado;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminOnly::class,
            'pos' => AdminOrEmpleado::class,
        ]);

        // Cabeceras de seguridad (nosniff, anti-clickjacking, HSTS sobre HTTPS...)
        $middleware->append(SecurityHeaders::class);

        // Rate limiting general para toda la API (limiter "api" en AppServiceProvider)
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
