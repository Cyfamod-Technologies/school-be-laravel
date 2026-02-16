<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Database\Seeders\FrontendPermissionSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

/**
 * Controller for seeding frontend permissions for a school.
 * 
 * Note: Permissions are now auto-seeded when listing permissions via PermissionController.
 * This controller provides explicit seeding/syncing endpoints for manual control.
 */
class PermissionSeedController extends Controller
{
    /**
     * Seed all frontend permissions for the current user's school.
     * 
     * This is idempotent - it will only create permissions that don't exist.
     */
    public function seed(Request $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $guardName = config('permission.default_guard', 'sanctum');

        try {
            $result = FrontendPermissionSeeder::seedForSchool($schoolId, $guardName);

            return response()->json([
                'message' => 'Permissions seeded successfully.',
                'data' => [
                    'created' => $result['created'],
                    'existing' => $result['existing'],
                    'total' => $result['total'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to seed permissions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the catalog of all available permissions.
     * 
     * Returns the full list of permissions from the FrontendPermissionSeeder
     * along with their descriptions, regardless of whether they've been seeded.
     */
    public function catalog(Request $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $guardName = config('permission.default_guard', 'sanctum');

        // Get existing permission IDs for this school
        $existingPermissions = Permission::query()
            ->where('school_id', $schoolId)
            ->where('guard_name', $guardName)
            ->get()
            ->keyBy('name');

        $catalog = [];
        foreach (FrontendPermissionSeeder::$permissions as $name => $description) {
            $existing = $existingPermissions->get($name);
            $catalog[] = [
                'name' => $name,
                'description' => $description,
                'id' => $existing?->id,
                'seeded' => $existing !== null,
            ];
        }

        return response()->json([
            'data' => $catalog,
            'meta' => [
                'total' => count($catalog),
                'seeded' => $existingPermissions->count(),
                'pending' => count($catalog) - $existingPermissions->count(),
            ],
        ]);
    }

    /**
     * Sync permissions: seed missing ones and return current state.
     * 
     * This is a combined endpoint that seeds missing permissions
     * and returns the full catalog with IDs.
     */
    public function sync(Request $request): JsonResponse
    {
        $schoolId = $this->resolveSchoolId($request);
        $guardName = config('permission.default_guard', 'sanctum');

        // Seed any missing permissions
        $seedResult = FrontendPermissionSeeder::seedForSchool($schoolId, $guardName);

        // Get all permissions for this school
        $permissions = Permission::query()
            ->where('school_id', $schoolId)
            ->where('guard_name', $guardName)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description ?? FrontendPermissionSeeder::getDescription($p->name),
            ]);

        return response()->json([
            'data' => $permissions,
            'meta' => [
                'synced' => true,
                'created' => $seedResult['created'],
                'total' => $permissions->count(),
            ],
        ]);
    }

    /**
     * Get the school ID from the authenticated user.
     */
    private function resolveSchoolId(Request $request): string
    {
        $schoolId = $request->user()->school_id;

        abort_if(empty($schoolId), 422, 'Authenticated user is not associated with a school.');

        return $schoolId;
    }
}
