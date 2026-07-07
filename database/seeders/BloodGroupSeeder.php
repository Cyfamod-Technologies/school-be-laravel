<?php

namespace Database\Seeders;

use App\Models\BloodGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BloodGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            'O-',
            'O+',
            'A+',
            'A-',
            'B+',
            'B-',
            'AB+',
            'AB-',
        ];

        foreach ($groups as $name) {
            BloodGroup::firstOrCreate(
                ['name' => $name],
                ['id' => (string) Str::uuid()]
            );
        }
    }
}
