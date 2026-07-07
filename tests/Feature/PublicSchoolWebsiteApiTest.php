<?php

use App\Models\School;
use App\Models\SchoolWebsite;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: School, 1: SchoolWebsite}
 */
function createSchoolForPublicWebsiteApi(
    string $status = 'published',
    bool $hasPublicationTimestamp = true
): array {
    $school = School::factory()->create();

    $website = SchoolWebsite::query()->create([
        'school_id' => $school->id,
        'contract_version' => 1,
        'status' => $status,
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
            'eyebrow' => 'Welcome to Bright Future Academy',
            'title' => 'A better future starts here',
            'description' => 'Supporting every learner.',
            'imageUrl' => '/images/hero.jpg',

            'primaryAction' => [
                'label' => 'Apply for admission',
                'href' => '/schools/bright-future#admissions',
            ],

            'secondaryAction' => [
                'label' => 'Explore our school',
                'href' => '/schools/bright-future#about',
            ],

            'trustItems' => [
                'Safe learning environment',
                'Experienced educators',
            ],

            'infoCard' => [
                'label' => 'Our commitment',
                'title' => 'Every child deserves to thrive.',
                'description' => 'A caring school community.',
            ],
        ],

        'highlights' => [
            [
                'id' => 'safe-learning',
                'title' => 'Safe learning',
                'description' => 'A secure learning environment.',
                'iconUrl' => null,
            ],
        ],

        'about' => [
            'eyebrow' => 'About our school',
            'title' => 'Building confident learners',
            'description' => 'A supportive school community.',
            'imageUrl' => null,
            'mission' => 'To provide excellent education.',
            'vision' => 'To raise responsible learners.',
        ],

        'programmes' => [
            [
                'id' => 'early-years',
                'name' => 'Early Years',
                'summary' => 'A strong start for young learners.',
                'imageUrl' => null,
            ],
        ],

        'admissions' => [
            'eyebrow' => 'Admissions',
            'title' => 'Join our school',
            'description' => 'Begin your learning journey with us.',

            'action' => [
                'label' => 'Apply now',
                'href' => '/schools/bright-future#admissions',
            ],
        ],

        'contact' => [
            'address' => '12 Learning Avenue',
            'phone' => '+234 000 000 0000',
            'email' => 'hello@brightfuture.example',
            'mapUrl' => null,
        ],

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

        'published_at' => $hasPublicationTimestamp
            ? now()
            : null,
    ]);

    return [$school, $website];
}

it('returns a published school website without authentication', function () {
    [$school] = createSchoolForPublicWebsiteApi();

    $slug = (string) $school->getAttribute('slug');

    $response = $this->getJson(
        "/api/v1/public/schools/{$slug}/website"
    );

    $response
        ->assertOk()
        ->assertJsonPath('contractVersion', 1)
        ->assertJsonPath('school.slug', $slug)
        ->assertJsonPath('website.status', 'published')
        ->assertJsonPath('website.themeKey', 'kidza-home-2')
        ->assertJsonPath(
            'website.branding.primaryColor',
            '#2563eb'
        )
        ->assertJsonPath(
            'website.header.tagline',
            'Learn • Grow • Succeed'
        );

    $payload = $response->json();

    expect($payload)
        ->not->toHaveKey('data')
        ->and($payload)
        ->not->toHaveKey('id')
        ->and($payload['school'])
        ->not->toHaveKey('id')
        ->and($payload['website'])
        ->not->toHaveKey('schoolId')
        ->and($payload['website'])
        ->not->toHaveKey('createdAt');
});

it('returns not found for a draft website', function () {
    [$school] = createSchoolForPublicWebsiteApi(
        status: 'draft',
        hasPublicationTimestamp: false
    );

    $slug = (string) $school->getAttribute('slug');

    $this
        ->getJson(
            "/api/v1/public/schools/{$slug}/website"
        )
        ->assertNotFound();
});

it('returns not found for an unpublished website', function () {
    [$school] = createSchoolForPublicWebsiteApi(
        status: 'unpublished',
        hasPublicationTimestamp: false
    );

    $slug = (string) $school->getAttribute('slug');

    $this
        ->getJson(
            "/api/v1/public/schools/{$slug}/website"
        )
        ->assertNotFound();
});

it('returns not found when published_at is missing', function () {
    [$school] = createSchoolForPublicWebsiteApi(
        status: 'published',
        hasPublicationTimestamp: false
    );

    $slug = (string) $school->getAttribute('slug');

    $this
        ->getJson(
            "/api/v1/public/schools/{$slug}/website"
        )
        ->assertNotFound();
});

it('returns not found for an unknown school slug', function () {
    $this
        ->getJson(
            '/api/v1/public/schools/unknown-school/website'
        )
        ->assertNotFound();
});
