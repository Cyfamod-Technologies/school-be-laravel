<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $school = \App\Models\School::factory()->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'school_id' => $school->id,
        ]);
    }
}
