<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\Rbac\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RbacManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_role_with_permissions(): void
    {
        [$school, $admin] = $this->createSchoolAdmin();

        $permission = Permission::query()->create([
            'name' => 'attendance.manage',
            'guard_name' => config('permission.default_guard', 'sanctum'),
            'school_id' => $school->id,
            'description' => 'Manage attendance records',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/roles', [
            'name' => 'attendance_manager',
            'description' => 'Manages attendance workflows',
            'permissions' => [$permission->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'attendance_manager');

        $this->assertDatabaseHas('roles', [
            'name' => 'attendance_manager',
            'school_id' => $school->id,
        ]);

        $createdRoleId = Role::query()->where('name', 'attendance_manager')->where('school_id', $school->id)->value('id');
        $this->assertNotNull($createdRoleId);
        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $createdRoleId,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_user_without_manage_permission_cannot_create_role(): void
    {
        [$school, $admin] = $this->createSchoolAdmin();

        $teacher = User::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => 'Teacher User',
            'email' => 'teacher@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $staffRole = Role::query()->updateOrCreate(
            [
                'name' => 'staff',
                'school_id' => $school->id,
            ],
            [
                'guard_name' => config('permission.default_guard', 'sanctum'),
                'description' => 'General staff role',
            ]
        );

        $teacher->assignRole($staffRole);

        Sanctum::actingAs($teacher);

        $response = $this->postJson('/api/v1/roles', [
            'name' => 'unauthorized_role',
            'description' => 'Should not be created',
            'permissions' => [],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('roles', [
            'name' => 'unauthorized_role',
            'school_id' => $school->id,
        ]);
    }

    public function test_admin_can_assign_roles_to_user(): void
    {
        [$school, $admin] = $this->createSchoolAdmin();

        $teacher = User::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => 'Subject Teacher',
            'email' => 'subject.teacher@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $role = Role::query()->updateOrCreate(
            [
                'name' => 'subject_teacher',
                'school_id' => $school->id,
            ],
            [
                'guard_name' => config('permission.default_guard', 'sanctum'),
                'description' => 'Handles subject allocations',
            ]
        );

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/users/{$teacher->id}/roles", [
            'roles' => [$role->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user_id', $teacher->id);

        $this->assertTrue($teacher->fresh()->hasRole('subject_teacher'));
    }

    private function createSchoolAdmin(): array
    {
        $school = School::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Test School',
            'slug' => 'test-school',
            'subdomain' => 'test-school',
            'address' => '123 Test Street',
            'status' => 'active',
        ]);

        $admin = User::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        /** @var RbacService $rbac */
        $rbac = app(RbacService::class);
        $rbac->bootstrapForSchool($school, $admin);

        return [$school, $admin];
    }
}
