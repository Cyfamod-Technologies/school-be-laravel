<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class CBTPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define CBT permissions
        $permissions = [
            'cbt.view',
            'cbt.manage',
            'cbt.create',
            'cbt.edit',
            'cbt.delete',
            'cbt.publish',
            'cbt.close',
            'cbt.view_results',
            'cbt.manage_results',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['name' => $permission, 'guard_name' => 'sanctum']
            );
        }
    }
}
