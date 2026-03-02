<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Admin access is required.',
            ], 403);
        }

        $role = strtolower((string) ($user->role ?? ''));
        $isAdmin = in_array($role, ['admin', 'super_admin', 'owner'], true)
            || $user->hasAnyRole(['admin', 'super_admin', 'owner']);

        if (! $isAdmin) {
            return response()->json([
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
