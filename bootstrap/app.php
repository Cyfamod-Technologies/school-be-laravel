<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        then: function (): void {
            require __DIR__.'/../routes/admin.php';
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdminAccess::class,
        ]);

        $middleware->redirectGuestsTo(function () {
            $fallback = '/school-fe-template/update/v10/login.html';
            $url = config('app.frontend_login_url', $fallback);

            return $url;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
