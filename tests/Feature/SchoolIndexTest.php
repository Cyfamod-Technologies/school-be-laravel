<?php

use App\Models\School;

use function Pest\Laravel\getJson;

it('lists the name and logo of every registered school', function () {
    School::factory()->create([
        'name' => 'Zion Academy',
        'logo_url' => 'schools/logos/zion.png',
        'status' => 'active',
    ]);

    School::factory()->create([
        'name' => 'Alpha College',
        'logo_url' => null,
        'status' => 'inactive',
    ]);

    getJson('/api/v1/schools')
        ->assertOk()
        ->assertExactJson([
            'schools' => [
                [
                    'name' => 'Alpha College',
                    'logo_url' => null,
                ],
                [
                    'name' => 'Zion Academy',
                    'logo_url' => rtrim(config('app.url'), '/').'/storage/schools/logos/zion.png',
                ],
            ],
        ]);
});

it('does not require authentication to list registered schools', function () {
    getJson('/api/v1/schools')
        ->assertOk()
        ->assertExactJson(['schools' => []]);
});
