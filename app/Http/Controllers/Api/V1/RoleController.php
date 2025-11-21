<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max((int) $request->input('per_page', 15), 1);
        $schoolId = $this->resolveSchoolId($request);

        $roles = Role::query()
            ->where('school_id', $schoolId)
            ->where('guard_name', config('permission.default_guard', 'sanctum'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->input('search');
                $query->where(fn ($builder) => $builder
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%"));
            })
            ->with(['permissions' => function ($relation) use ($schoolId) {
                $relation->where('school_id', $schoolId)
                    ->where('guard_name', config('permission.default_guard', 'sanctum'));
            }])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return RoleResource::collection($roles);
    }

    public function store(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('roles')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('guard_name', config('permission.default_guard', 'sanctum'))
                ),
            ],
            'description' => ['nullable', 'string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [
                'integer',
                Rule::exists('permissions', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('guard_name', config('permission.default_guard', 'sanctum'))
                ),
            ],
        ]);

        $permissions = $validated['permissions'] ?? [];
        unset($validated['permissions']);

        $validated['school_id'] = $schoolId;
        $validated['guard_name'] = config('permission.default_guard', 'sanctum');

        /** @var \App\Models\Role $role */
        $role = DB::transaction(function () use ($validated, $permissions, $schoolId) {
            $role = Role::query()->create($validated);

            $permissionModels = Permission::query()
                ->where('school_id', $schoolId)
                ->where('guard_name', config('permission.default_guard', 'sanctum'))
                ->whereIn('id', $permissions)
                ->get();

            $role->syncPermissions($permissionModels);

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return (new RoleResource($role->load('permissions')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Role $role)
    {
        $this->assertRoleBelongsToSchool($role, $this->resolveSchoolId($request));

        return new RoleResource($role->load(['permissions' => function ($relation) use ($role) {
            $relation->where('school_id', $role->school_id)
                ->where('guard_name', config('permission.default_guard', 'sanctum'));
        }]));
    }

    public function update(Request $request, Role $role)
    {
        $this->assertRoleBelongsToSchool($role, $this->resolveSchoolId($request));

        $schoolId = $role->school_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('roles')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('guard_name', config('permission.default_guard', 'sanctum'))
                )->ignore($role->id),
            ],
            'description' => ['nullable', 'string'],
            'permissions.*' => [
                'integer',
                Rule::exists('permissions', 'id')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('guard_name', config('permission.default_guard', 'sanctum'))
                ),
            ],
        ]);

        $permissions = array_key_exists('permissions', $validated) ? $validated['permissions'] : null;
        unset($validated['permissions']);

        DB::transaction(function () use ($role, $validated, $permissions, $schoolId) {
            $role->update(array_merge($validated, [
                'guard_name' => config('permission.default_guard', 'sanctum'),
            ]));

            if ($permissions !== null) {
                // For teacher role, ensure locked permissions are always included
                if (strtolower($role->name) === 'teacher') {
                    $lockedPermissionNames = ['profile.view', 'profile.edit', 'profile.password'];
                    $lockedPermissions = Permission::query()
                        ->where('school_id', $schoolId)
                        ->where('guard_name', config('permission.default_guard', 'sanctum'))
                        ->whereIn('name', $lockedPermissionNames)
                        ->pluck('id')
                        ->toArray();
                    
                    // Merge locked permissions with provided permissions
                    $permissions = array_unique(array_merge($permissions, $lockedPermissions));
                }

                $permissionModels = Permission::query()
                    ->where('school_id', $schoolId)
                    ->where('guard_name', config('permission.default_guard', 'sanctum'))
                    ->whereIn('id', $permissions)
                    ->get();

                $role->syncPermissions($permissionModels);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return new RoleResource($role->fresh('permissions'));
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->assertRoleBelongsToSchool($role, $this->resolveSchoolId($request));

        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a role that is assigned to users.',
            ], 409);
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(null, 204);
    }

    private function resolveSchoolId(Request $request): string
    {
        $schoolId = $request->user()->school_id;

        abort_if(empty($schoolId), 422, 'Authenticated user is not associated with a school.');

        return $schoolId;
    }

    private function assertRoleBelongsToSchool(Role $role, string $schoolId): void
    {
        abort_unless($role->school_id === $schoolId, 404, 'Role not found.');
    }
}
