<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Ensure the authenticated user has the required permission(s). Admin and super admin
     * roles are treated as full-access overrides.
     *
     * @param  string|string[]  $permission
     */
    protected function ensurePermission(Request $request, string|array $permission): void
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'You do not have permission to perform this action.');
        }

        $role = strtolower((string) ($user->role ?? ''));
        if (in_array($role, ['admin', 'super_admin'], true)) {
            return;
        }

        /** @var \Spatie\Permission\PermissionRegistrar $registrar */
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $previousTeamId = method_exists($registrar, 'getPermissionsTeamId')
            ? $registrar->getPermissionsTeamId()
            : null;

        if (! empty($user->school_id)) {
            $registrar->setPermissionsTeamId($user->school_id);
        }

        $permissions = (array) $permission;
        try {
            foreach ($permissions as $entry) {
                if ($entry !== '' && $user->can($entry)) {
                    return;
                }
            }
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}
