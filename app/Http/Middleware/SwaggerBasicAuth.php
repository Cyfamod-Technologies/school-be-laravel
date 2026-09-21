<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwaggerBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredUsername = (string) config('l5-swagger.basic_auth.username');
        $configuredPassword = (string) config('l5-swagger.basic_auth.password');

        if ($configuredUsername === '' || $configuredPassword === '') {
            return response('Swagger documentation is not configured.', 503, [
                'Cache-Control' => 'no-store',
            ]);
        }

        $providedUsername = (string) ($request->getUser() ?? '');
        $providedPassword = (string) ($request->getPassword() ?? '');

        if (
            ! hash_equals($configuredUsername, $providedUsername)
            || ! hash_equals($configuredPassword, $providedPassword)
        ) {
            return response('Authentication required.', 401, [
                'WWW-Authenticate' => 'Basic realm="School API Documentation", charset="UTF-8"',
                'Cache-Control' => 'no-store',
            ]);
        }

        return $next($request);
    }
}
