<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed foundation data first
        $this->call([
            BloodGroupSeeder::class,
            DefaultGradeScaleSeeder::class,
            CountryStateLgaSeeder::class,
        ]);

        // Seed RBAC permissions
        $this->call([
            CBTPermissionSeeder::class,
            FrontendPermissionSeeder::class,
        ]);

        // Seed comprehensive demo school data
        $this->call([
            ComprehensiveSchoolSeeder::class,
        ]);
    }
}
