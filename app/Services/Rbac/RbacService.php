<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

class RbacService
{
    /**
     * Default permissions every school should start with.
     */
    private array $corePermissions = [
        ['name' => 'dashboard.view', 'description' => 'View dashboard overview'],
        ['name' => 'profile.view', 'description' => 'View own profile'],
        ['name' => 'profile.edit', 'description' => 'Update profile details'],
        ['name' => 'profile.password', 'description' => 'Change profile password'],

        ['name' => 'students.view', 'description' => 'List and view students'],
        ['name' => 'students.create', 'description' => 'Create students'],
        ['name' => 'students.update', 'description' => 'Update students'],
        ['name' => 'students.delete', 'description' => 'Delete students'],
        ['name' => 'students.import', 'description' => 'Bulk import students'],
        ['name' => 'students.promote', 'description' => 'Promote students'],
        ['name' => 'students.results.print', 'description' => 'Print student results'],

        ['name' => 'parents.view', 'description' => 'List and view parents'],
        ['name' => 'parents.manage', 'description' => 'Create or update parents'],

        ['name' => 'staff.view', 'description' => 'List and view staff'],
        ['name' => 'staff.create', 'description' => 'Create staff'],
        ['name' => 'staff.update', 'description' => 'Update staff'],
        ['name' => 'staff.delete', 'description' => 'Delete staff'],
        ['name' => 'staff.attendance', 'description' => 'Manage staff attendance'],

        ['name' => 'classes.manage', 'description' => 'Manage classes and arms'],
        ['name' => 'subjects.manage', 'description' => 'Manage subjects'],
        ['name' => 'subject.assignments', 'description' => 'Assign subjects to classes and staff'],

        ['name' => 'sessions.manage', 'description' => 'Manage academic sessions and terms'],
        ['name' => 'assessment.manage', 'description' => 'Configure assessment components'],
        ['name' => 'skills.manage', 'description' => 'Manage skill categories and ratings'],

        ['name' => 'attendance.students', 'description' => 'Manage student attendance'],
        ['name' => 'attendance.staff', 'description' => 'Manage staff attendance'],

        ['name' => 'fees.items', 'description' => 'Manage fee items'],
        ['name' => 'fees.structures', 'description' => 'Manage fee structures'],
        ['name' => 'fees.bank-details', 'description' => 'Manage bank details'],

        ['name' => 'result.pin.manage', 'description' => 'Manage result pins'],
        ['name' => 'analytics.academics', 'description' => 'View academic analytics dashboard'],

        ['name' => 'promotions.history', 'description' => 'View promotion history'],
        ['name' => 'messages.manage', 'description' => 'Manage messaging'],

        ['name' => 'permissions.view', 'description' => 'View permissions'],
        ['name' => 'permissions.manage', 'description' => 'Create, update and delete permissions'],
        ['name' => 'roles.view', 'description' => 'View roles'],
        ['name' => 'roles.manage', 'description' => 'Create, update and delete roles'],
        ['name' => 'users.assignRoles', 'description' => 'Assign roles to users'],
    ];

    public function bootstrapForSchool(School $school, User $admin): void
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = method_exists($registrar, 'getPermissionsTeamId')
            ? $registrar->getPermissionsTeamId()
            : null;

        $registrar->setPermissionsTeamId($school->id);
        $registrar->forgetCachedPermissions();

        $guard = config('permission.default_guard', 'sanctum');

        Permission::query()
            ->where('school_id', $school->id)
            ->where('guard_name', '!=', $guard)
            ->update(['guard_name' => $guard]);

        Role::query()
            ->where('school_id', $school->id)
            ->where('guard_name', '!=', $guard)
            ->update(['guard_name' => $guard]);

        $this->syncCorePermissions($school);
        $adminRole = $this->ensureAdminRole($school);
        $superAdminRole = $this->ensureSuperAdminRole($school);

        $this->syncAdminPermissions($school);
        $this->syncSuperAdminPermissions($school);

        Role::query()->updateOrCreate(
            [
                'name' => 'staff',
                'school_id' => $school->id,
            ],
            [
                'guard_name' => $guard,
                'description' => 'School staff',
            ]
        );

        Role::query()->updateOrCreate(
            [
                'name' => 'parent',
                'school_id' => $school->id,
            ],
            [
                'guard_name' => $guard,
                'description' => 'Parent or guardian',
            ]
        );

        if (! $admin->hasRole($adminRole)) {
            $admin->assignRole($adminRole);
        }

        $currentRoleNames = $admin->roles()
            ->where('guard_name', $guard)
            ->pluck('name');

        if ($superAdminRole && ($admin->role === 'super_admin' || $currentRoleNames->contains('super_admin'))) {
            $admin->assignRole($superAdminRole);
            $currentRoleNames = $currentRoleNames->push('super_admin')->unique();
        }

        $roleNamesForColumn = $admin->roles()
            ->where('guard_name', $guard)
            ->pluck('name');

        if ($admin->role === 'super_admin' && ! $roleNamesForColumn->contains('super_admin') && $superAdminRole) {
            $roleNamesForColumn = $roleNamesForColumn->push('super_admin');
        }

        $activeRoleName = $roleNamesForColumn
            ->sortBy(fn ($name) => $name === 'super_admin' ? 0 : 1)
            ->first();

        $admin->forceFill(['role' => $activeRoleName ?? $adminRole->name])->save();

        $registrar->setPermissionsTeamId($previousTeam);
    }

    public function syncCorePermissions(School $school): Collection
    {
        $guard = config('permission.default_guard', 'sanctum');

        return collect($this->corePermissions)->map(fn (array $attributes) => Permission::query()->updateOrCreate(
            [
                'school_id' => $school->id,
                'name' => $attributes['name'],
            ],
            [
                'guard_name' => $guard,
                'description' => $attributes['description'],
            ]
        ));
    }

    public function ensureAdminRole(School $school): Role
    {
        $guard = config('permission.default_guard', 'sanctum');

        return Role::query()->updateOrCreate(
            [
                'name' => 'admin',
                'school_id' => $school->id,
            ],
            [
                'guard_name' => $guard,
                'description' => 'School administrator',
            ]
        );
    }

    public function ensureSuperAdminRole(School $school): ?Role
    {
        $guard = config('permission.default_guard', 'sanctum');

        return Role::query()->updateOrCreate(
            [
                'name' => 'super_admin',
                'school_id' => $school->id,
            ],
            [
                'guard_name' => $guard,
                'description' => 'Platform super administrator',
            ]
        );
    }

    public function syncAdminPermissions(School $school): void
    {
        $adminRole = $this->ensureAdminRole($school);

        $permissions = Permission::query()
            ->where('school_id', $school->id)
            ->where('guard_name', config('permission.default_guard', 'sanctum'))
            ->get();

        $adminRole->syncPermissions($permissions);
    }

    public function syncSuperAdminPermissions(School $school): void
    {
        $superAdminRole = $this->ensureSuperAdminRole($school);

        if (! $superAdminRole) {
            return;
        }

        $permissions = Permission::query()
            ->where('school_id', $school->id)
            ->where('guard_name', config('permission.default_guard', 'sanctum'))
            ->get();

        $superAdminRole->syncPermissions($permissions);
    }
}
