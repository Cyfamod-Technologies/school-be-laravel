<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\Rbac\RbacService;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $school = \App\Models\School::firstOrCreate(
            ['subdomain' => 'demo-school'],
            [
                'name' => 'Demo School',
                'slug' => 'demo-school',
                'address' => '123 Demo Street',
                'email' => 'demo-school@example.com',
                'phone' => '000-000-0000',
            ]
        );

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId($school->id);

        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'status' => 'active',
                'school_id' => $school->id,
            ]
        );

        /** @var RbacService $rbac */
        $rbac = app(RbacService::class);
        $rbac->bootstrapForSchool($school, $user);

        $adminRole = Role::query()->where('name', 'admin')->where('school_id', $school->id)->first();

        $superAdminRole = Role::query()->updateOrCreate(
            [
                'name' => 'super_admin',
                'school_id' => $school->id,
            ],
            [
                'guard_name' => config('permission.default_guard', 'sanctum'),
                'description' => 'Platform super administrator',
            ]
        );

        if ($adminRole) {
            $superAdminRole->syncPermissions($adminRole->permissions);
        }

        if (! $user->hasRole($superAdminRole)) {
            $user->assignRole($superAdminRole);
        }

        $user->forceFill(['role' => 'super_admin'])->save();

        $registrar->setPermissionsTeamId(null);

        $this->call([
            BloodGroupSeeder::class,
            DefaultGradeScaleSeeder::class,
            CountryStateLgaSeeder::class,
        ]);
    }
}
