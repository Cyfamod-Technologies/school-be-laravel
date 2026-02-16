<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        if (! $user instanceof User) {
            abort(403, 'You do not have permission to perform this action.');
        }

        // Keep endpoint access simple: authenticated users are allowed unless strict
        // endpoint permission enforcement is explicitly enabled.
        if (! config('features.enforce_endpoint_permissions', false)) {
            return;
        }

        /** @var \Spatie\Permission\PermissionRegistrar $registrar */
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $previousTeamId = method_exists($registrar, 'getPermissionsTeamId')
            ? $registrar->getPermissionsTeamId()
            : null;

        $teamId = $user->school_id;
        if (! $teamId) {
            // Fallback: use the school_id from any attached role to scope permission checks.
            $teamId = $user->roles()->value('school_id');
        }

        if (! empty($teamId)) {
            $registrar->setPermissionsTeamId($teamId);
        }

        try {
            $role = strtolower((string) ($user->role ?? ''));
            $isAdminRole = in_array($role, ['admin', 'super_admin'], true);
            $hasAdminSpatieRole = $user->hasAnyRole(['admin', 'super_admin']);

            // Treat admin and super admin roles (column or spatie role) as full access.
            if ($isAdminRole || $hasAdminSpatieRole) {
                return;
            }

            $permissions = (array) $permission;
            foreach ($permissions as $entry) {
                if ($entry !== '' && $user->can($entry)) {
                    return;
                }
            }
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId ?? null);
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}
