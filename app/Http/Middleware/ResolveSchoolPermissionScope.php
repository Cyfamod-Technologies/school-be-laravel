<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class ResolveSchoolPermissionScope
{
    public function handle(Request $request, Closure $next)
    {
        /** @var \Spatie\Permission\PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $schoolId = optional($request->user())->school_id;

        $registrar->setPermissionsTeamId($schoolId);

        return $next($request);
    }
}
