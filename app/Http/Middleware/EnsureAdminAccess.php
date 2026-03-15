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
        
        // Strict Check: Only 'super_admin' or 'owner' roles are permitted for platform management
        $isAdmin = in_array($role, ['super_admin', 'owner'], true)
            || $user->hasAnyRole(['super_admin', 'owner']);

        if (! $isAdmin) {
            return response()->json([
                'message' => 'This area is restricted to Platform Administrators only.',
            ], 403);
        }

        return $next($request);
    }
}
