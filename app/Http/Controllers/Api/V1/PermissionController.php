<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Database\Seeders\FrontendPermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        // Allow up to 500 per page to fetch all permissions at once for role management
        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $schoolId = $this->resolveSchoolId($request);
        $guardName = config('permission.default_guard', 'sanctum');

        // Auto-seed permissions for this school if they don't exist
        // This ensures all schools have permissions without manual seeding
        $this->ensurePermissionsExist($schoolId, $guardName);

        $permissions = Permission::query()
            ->where('school_id', $schoolId)
            ->where('guard_name', $guardName)
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

    /**
     * Ensure all frontend permissions exist for the given school.
     * This is called automatically when listing permissions.
     */
    private function ensurePermissionsExist(string $schoolId, string $guardName): void
    {
        // Quick check: if the school already has permissions, skip seeding
        $existingCount = Permission::where('school_id', $schoolId)
            ->where('guard_name', $guardName)
            ->count();

        $catalogCount = count(FrontendPermissionSeeder::$permissions);

        // If school has all or most permissions, skip (allow some tolerance for updates)
        if ($existingCount >= $catalogCount) {
            return;
        }

        // Seed missing permissions for this school
        FrontendPermissionSeeder::seedForSchool($schoolId, $guardName);
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
