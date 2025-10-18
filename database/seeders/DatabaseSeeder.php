<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

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

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'role' => 'super_admin',
                'status' => 'active',
                'school_id' => $school->id,
            ]
        );

        $this->call([
            BloodGroupSeeder::class,
            DefaultGradeScaleSeeder::class,
            CountryStateLgaSeeder::class,
        ]);
    }
}
