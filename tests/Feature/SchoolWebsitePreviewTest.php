<?php

use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();
});

afterEach(function () {
    Schema::enableForeignKeyConstraints();
});

function actingAsSchoolWebsiteUserWithRealSchool(): array
{
    $school = School::factory()->create();

    $user = new User;
    $user->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'email' => Str::uuid().'@example.test',
    ]);

    Sanctum::actingAs($user);

    return [$user, $school];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createSchoolWebsiteFor(School $school, array $overrides = []): SchoolWebsite
{
    return SchoolWebsite::create(array_replace([
        'school_id' => $school->id,
        'contract_version' => 1,
        'status' => 'draft',
        'theme_key' => 'kidza-home-2',
        'branding' => ['primaryColor' => '#2563eb', 'secondaryColor' => '#f97316'],
        'seo' => ['title' => $school->name, 'description' => 'A caring learning community.', 'imageUrl' => null],
        'header' => ['welcomeText' => 'Welcome', 'utilityText' => 'Admissions open', 'tagline' => 'Learn and grow'],
        'hero' => [
            'eyebrow' => 'Welcome',
            'title' => $school->name,
            'description' => 'Supporting every learner.',
            'imageUrl' => null,
            'primaryAction' => ['label' => 'Apply', 'href' => '/apply'],
            'secondaryAction' => ['label' => 'Learn more', 'href' => '/about'],
            'trustItems' => ['Quality education'],
            'infoCard' => ['label' => 'Est.', 'title' => '2020', 'description' => 'Years of excellence'],
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
            'highlights' => false,
            'about' => false,
            'programmes' => false,
            'admissions' => false,
            'contact' => false,
        ],
        'published_at' => null,
    ], $overrides));
}

it('rejects unauthenticated preview link requests', function () {
    $this->postJson('/api/v1/school/website/preview-link')
        ->assertUnauthorized();
});

it('requires an existing website before a preview link can be issued', function () {
    actingAsSchoolWebsiteUserWithRealSchool();

    $this->postJson('/api/v1/school/website/preview-link')
        ->assertNotFound();
});

it('issues a signed preview link for the authenticated school', function () {
    [, $school] = actingAsSchoolWebsiteUserWithRealSchool();

    createSchoolWebsiteFor($school);

    $response = $this->postJson('/api/v1/school/website/preview-link')
        ->assertOk()
        ->assertJsonStructure(['url', 'expiresAt']);

    expect($response->json('url'))
        ->toContain('/api/v1/public/schools/'.$school->slug.'/website/preview')
        ->toContain('signature=');
});

it('reveals draft content through a validly signed preview link', function () {
    [, $school] = actingAsSchoolWebsiteUserWithRealSchool();

    createSchoolWebsiteFor($school, [
        'status' => 'draft',
        'published_at' => null,
        'hero' => [
            'eyebrow' => 'Preview eyebrow',
            'title' => 'Preview-only title',
            'description' => 'Preview description.',
            'imageUrl' => null,
            'primaryAction' => ['label' => 'Apply', 'href' => '/apply'],
            'secondaryAction' => ['label' => 'Learn more', 'href' => '/about'],
            'trustItems' => ['Quality education'],
            'infoCard' => ['label' => 'Est.', 'title' => '2020', 'description' => 'Years'],
        ],
    ]);

    $previewUrl = $this->postJson('/api/v1/school/website/preview-link')
        ->assertOk()
        ->json('url');

    // The public (non-preview) endpoint must not reveal the draft.
    $this->getJson("/api/v1/public/schools/{$school->slug}/website")
        ->assertNotFound();

    // The signed preview endpoint must reveal it, with no auth header.
    $preview = $this->withHeader('Accept', 'application/json')
        ->get($previewUrl)
        ->assertOk();

    expect($preview->json('website.status'))->toBe('draft');
    expect($preview->json('website.hero.title'))->toBe('Preview-only title');
});

it('rejects a preview request with a missing signature', function () {
    [, $school] = actingAsSchoolWebsiteUserWithRealSchool();

    createSchoolWebsiteFor($school);

    $this->getJson("/api/v1/public/schools/{$school->slug}/website/preview")
        ->assertForbidden();
});

it('rejects a preview request with a tampered signature', function () {
    [, $school] = actingAsSchoolWebsiteUserWithRealSchool();

    createSchoolWebsiteFor($school);

    $previewUrl = $this->postJson('/api/v1/school/website/preview-link')
        ->assertOk()
        ->json('url');

    $this->get($previewUrl.'-tampered')->assertForbidden();
});

it('rejects a signed preview link reused for a different school slug', function () {
    [, $school] = actingAsSchoolWebsiteUserWithRealSchool();
    $otherSchool = School::factory()->create();

    createSchoolWebsiteFor($school);

    $previewUrl = $this->postJson('/api/v1/school/website/preview-link')
        ->assertOk()
        ->json('url');

    $swappedUrl = str_replace($school->slug, $otherSchool->slug, $previewUrl);

    $this->get($swappedUrl)->assertForbidden();
});
