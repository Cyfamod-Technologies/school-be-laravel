<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the one endpoint sms-enterprise-edition calls into this backend
 * (flipping a school's `activated` flag). Mirrors the identical middleware
 * in sms-enterprise-edition -- both sides check the same shared secret,
 * set identically in each app's .env as INTERNAL_SHARED_SECRET.
 */
class VerifyInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.internal_shared_secret');
        $provided = $request->header('X-Internal-Secret');

        if (! $expected || ! $provided || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
