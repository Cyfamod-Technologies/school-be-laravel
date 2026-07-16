<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            'internal-secret' => \App\Http\Middleware\VerifyInternalSecret::class,
        ]);

        // Trust whatever reverse proxy sits in front of this app. Without
        // this, Laravel has no way to know a request originally arrived
        // over HTTPS -- it only sees the internal http:// hop the proxy
        // forwards to. That silently breaks anything that signs the full
        // URL (temporary signed routes, used by the website preview-link
        // feature): the signature gets stamped with the wrong scheme at
        // generation time and can never validate, on any link, regardless
        // of freshness. `at: '*'` trusts any proxy IP -- tighten to a
        // specific IP/CIDR if this app ever sits behind a proxy that isn't
        // fully trusted (e.g. a shared load balancer, not one you control).
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->redirectGuestsTo(function () {
            $fallback = '/school-fe-template/update/v10/login.html';
            $url = config('app.frontend_login_url', $fallback);

            return $url;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
