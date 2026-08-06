<?php

use App\Http\Resources\SchoolWebsiteResource;
use App\Models\SchoolWebsite;
use Illuminate\Support\Carbon;

it('transforms a school website into the admin API structure', function () {
    $website = new SchoolWebsite([
        'school_id' => '11111111-1111-4111-8111-111111111111',
        'contract_version' => 1,
        'status' => 'draft',
        'theme_key' => 'kidza-home-2',

        'branding' => [
            'primaryColor' => '#2563eb',
            'secondaryColor' => '#f97316',
        ],

        'seo' => [
            'title' => 'Bright Future Academy',
            'description' => 'A caring learning community.',
            'imageUrl' => null,
        ],

        'header' => [
            'welcomeText' => 'Welcome to Bright Future Academy',
            'utilityText' => 'A caring place to learn and grow',
            'tagline' => 'Learn • Grow • Succeed',
        ],

        'hero' => [
            'eyebrow' => 'Welcome',
            'title' => 'A better future starts here',
            'description' => 'Supporting every learner.',
        ],

        'highlights' => [],
        'about' => [],
        'programmes' => [],
        'admissions' => [],
        'contact' => [],

        'social_links' => [
            'facebook' => null,
            'instagram' => null,
            'linkedin' => null,
            'youtube' => null,
            'x' => null,
        ],

        'enabled_sections' => [
            'hero' => true,
            'highlights' => true,
            'about' => true,
            'programmes' => true,
            'admissions' => true,
            'contact' => true,
        ],

        'published_at' => null,
    ]);

    $website->id = '22222222-2222-4222-8222-222222222222';
    $website->created_at = Carbon::parse('2026-07-01T08:00:00Z');
    $website->updated_at = Carbon::parse('2026-07-02T09:30:00Z');

    $payload = (new SchoolWebsiteResource($website))
        ->resolve(request());

    expect($payload)
        ->toMatchArray([
            'id' => '22222222-2222-4222-8222-222222222222',
            'schoolId' => '11111111-1111-4111-8111-111111111111',
            'contractVersion' => 1,
            'status' => 'draft',
            'themeKey' => 'kidza-home-2',
            'branding' => [
                'primaryColor' => '#2563eb',
                'secondaryColor' => '#f97316',
            ],
            'socialLinks' => [
                'facebook' => null,
                'instagram' => null,
                'linkedin' => null,
                'youtube' => null,
                'x' => null,
            ],
            'enabledSections' => [
                'hero' => true,
                'highlights' => true,
                'about' => true,
                'programmes' => true,
                'admissions' => true,
                'contact' => true,
            ],
            'publishedAt' => null,
            'createdAt' => '2026-07-01T08:00:00.000000Z',
            'updatedAt' => '2026-07-02T09:30:00.000000Z',
        ]);

    expect(array_key_exists('school_id', $payload))->toBeFalse()
        ->and(array_key_exists('contract_version', $payload))->toBeFalse()
        ->and(array_key_exists('theme_key', $payload))->toBeFalse()
        ->and(array_key_exists('social_links', $payload))->toBeFalse()
        ->and(array_key_exists('enabled_sections', $payload))->toBeFalse();
});
