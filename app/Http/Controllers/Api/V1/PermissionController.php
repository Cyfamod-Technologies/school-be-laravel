<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max((int) $request->input('per_page', 15), 1);
        $schoolId = $this->resolveSchoolId($request);

        $permissions = Permission::query()
            ->where('school_id', $schoolId)
            ->where('guard_name', config('permission.default_guard', 'sanctum'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->input('search');
                $query->where(fn ($builder) => $builder
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return PermissionResource::collection($permissions);
    }

    public function store(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('permissions')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('guard_name', config('permission.default_guard', 'sanctum'))
                ),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $permission = Permission::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'school_id' => $schoolId,
            'guard_name' => config('permission.default_guard', 'sanctum'),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return (new PermissionResource($permission))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Permission $permission)
    {
        $this->assertPermissionBelongsToSchool($permission, $this->resolveSchoolId($request));

        return new PermissionResource($permission);
    }

    public function update(Request $request, Permission $permission)
    {
        $this->assertPermissionBelongsToSchool($permission, $this->resolveSchoolId($request));

        $schoolId = $permission->school_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('permissions')->where(fn ($query) => $query
                    ->where('school_id', $schoolId)
                    ->where('guard_name', config('permission.default_guard', 'sanctum'))
                )->ignore($permission->id),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $permission->update(array_merge($validated, [
            'guard_name' => config('permission.default_guard', 'sanctum'),
        ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return new PermissionResource($permission->fresh());
    }

    public function destroy(Request $request, Permission $permission): JsonResponse
    {
        $this->assertPermissionBelongsToSchool($permission, $this->resolveSchoolId($request));

        if ($permission->roles()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a permission that is assigned to roles.',
            ], 409);
        }

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(null, 204);
    }

    private function resolveSchoolId(Request $request): string
    {
        $schoolId = $request->user()->school_id;
        abort_if(empty($schoolId), 422, 'Authenticated user is not associated with a school.');

        return $schoolId;
    }

    private function assertPermissionBelongsToSchool(Permission $permission, string $schoolId): void
    {
        abort_unless($permission->school_id === $schoolId, 404, 'Permission not found.');
    }
}
