<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class UserRoleController extends Controller
{
    public function index(Request $request, User $user)
    {
        $this->assertUserBelongsToSchool($user, $this->resolveSchoolId($request));

        $schoolId = $this->resolveSchoolId($request);
        $roles = $this->withTeamContext($schoolId, function () use ($user, $schoolId) {
            return $user->roles()
                ->where('roles.school_id', $schoolId)
                ->where('roles.guard_name', config('permission.default_guard', 'sanctum'))
                ->with('permissions')
                ->orderBy('name')
                ->get();
        });

        return RoleResource::collection($roles);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $this->assertUserBelongsToSchool($user, $schoolId);

        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('guard_name', config('permission.default_guard', 'sanctum'))
                ),
            ],
        ]);

        // Check if user currently has teacher role
        $userCurrentRoles = $this->withTeamContext($schoolId, function () use ($user, $schoolId) {
            return $user->roles()
                ->where('roles.school_id', $schoolId)
                ->where('roles.guard_name', config('permission.default_guard', 'sanctum'))
                ->get();
        });

        $hasTeacherRole = $userCurrentRoles->contains(function ($role) {
            return strtolower($role->name) === 'teacher';
        });

        // If user has teacher role, ensure it's included in the new roles
        if ($hasTeacherRole) {
            $teacherRole = Role::query()
                ->where('roles.school_id', $schoolId)
                ->where('roles.guard_name', config('permission.default_guard', 'sanctum'))
                ->where('name', 'teacher')
                ->first();

            if ($teacherRole && !in_array($teacherRole->id, $validated['roles'])) {
                $validated['roles'][] = $teacherRole->id;
            }
        }

        $roleModels = Role::query()
            ->where('roles.school_id', $schoolId)
            ->where('roles.guard_name', config('permission.default_guard', 'sanctum'))
            ->whereIn('id', $validated['roles'])
            ->orderBy('name')
            ->get();

        $this->withTeamContext($schoolId, function () use ($user, $roleModels) {
            $user->syncRoles($roleModels);
        });

        $rolePriority = collect([
            'super_admin',
            'admin',
            'teacher',
            'accountant',
            'staff',
            'parent',
        ]);

        $assignedRoles = $roleModels->pluck('name');

        $primaryRole = $rolePriority->first(function ($role) use ($assignedRoles) {
            return $assignedRoles->contains($role);
        });

        if (! $primaryRole) {
            $primaryRole = 'staff';
        }

        $user->forceFill(['role' => $primaryRole])->save();

        $assignedRoles = $this->withTeamContext($schoolId, function () use ($user, $schoolId) {
            return $user->roles()
                ->where('roles.school_id', $schoolId)
                ->where('roles.guard_name', config('permission.default_guard', 'sanctum'))
                ->with('permissions')
                ->orderBy('name')
                ->get();
        });

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'roles' => RoleResource::collection($assignedRoles),
            ],
        ]);
    }

    private function resolveSchoolId(Request $request): string
    {
        $schoolId = $request->user()->school_id;
        abort_if(empty($schoolId), 422, 'Authenticated user is not associated with a school.');

        return $schoolId;
    }

    private function assertUserBelongsToSchool(User $user, string $schoolId): void
    {
        abort_unless($user->school_id === $schoolId, 404, 'User not found.');
    }

    /**
     * Execute callbacks with Spatie team context scoped to the given school.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    private function withTeamContext(string $schoolId, callable $callback)
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = method_exists($registrar, 'getPermissionsTeamId')
            ? $registrar->getPermissionsTeamId()
            : null;

        $registrar->setPermissionsTeamId($schoolId);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
