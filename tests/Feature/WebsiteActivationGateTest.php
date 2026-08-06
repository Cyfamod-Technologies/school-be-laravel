<?php

use App\Models\School;
use App\Models\SchoolWebsite;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPublishedWebsiteFor(School $school): SchoolWebsite
{
    return SchoolWebsite::query()->create([
        'school_id' => $school->id,
        'contract_version' => 1,
        'status' => 'published',
        'theme_key' => 'kidza-home-2',
        'branding' => ['primaryColor' => '#2563eb', 'secondaryColor' => '#f97316'],
        'seo' => ['title' => 'Test School', 'description' => 'Test.', 'imageUrl' => null],
        'header' => ['welcomeText' => 'Welcome', 'utilityText' => 'Utility', 'tagline' => 'Tagline'],
        'hero' => [
            'eyebrow' => 'Eyebrow', 'title' => 'Title', 'description' => 'Description', 'imageUrl' => null,
            'primaryAction' => ['label' => 'Apply', 'href' => '/apply'],
            'secondaryAction' => ['label' => 'Learn more', 'href' => '/about'],
            'trustItems' => ['Trust item'],
            'infoCard' => ['label' => 'Label', 'title' => 'Title', 'description' => 'Description'],
        ],
        'highlights' => [['id' => 'a', 'title' => 'A', 'description' => 'A', 'iconUrl' => null]],
        'about' => ['eyebrow' => 'About', 'title' => 'About', 'description' => 'About', 'imageUrl' => null, 'mission' => 'M', 'vision' => 'V'],
        'programmes' => [['id' => 'a', 'name' => 'A', 'summary' => 'A', 'imageUrl' => null]],
        'admissions' => ['eyebrow' => 'A', 'title' => 'A', 'description' => 'A', 'action' => ['label' => 'Apply', 'href' => '/apply']],
        'contact' => ['address' => 'Addr', 'phone' => '000', 'email' => 'a@a.com', 'mapUrl' => null],
        'social_links' => ['facebook' => null, 'instagram' => null, 'linkedin' => null, 'youtube' => null, 'x' => null],
        'enabled_sections' => ['hero' => true, 'highlights' => true, 'about' => true, 'programmes' => true, 'admissions' => true, 'contact' => true],
        'published_at' => now(),
    ]);
}

it('serves the public website when published and activated', function () {
    $school = School::factory()->create(['activated' => true]);
    createPublishedWebsiteFor($school);

    $this->getJson("/api/v1/public/schools/{$school->slug}/website")
        ->assertOk();
});

it('blocks the public website when published but not yet activated', function () {
    $school = School::factory()->create(['activated' => false]);
    createPublishedWebsiteFor($school);

    // This is the loophole fix -- published alone used to be enough.
    $this->getJson("/api/v1/public/schools/{$school->slug}/website")
        ->assertNotFound();
});

it('resolves a school by its custom domain', function () {
    $school = School::factory()->create(['custom_domain' => 'hill-top.com.ng']);

    $this->getJson('/api/v1/public/schools/resolve-domain?domain=hill-top.com.ng')
        ->assertOk()
        ->assertJsonPath('slug', $school->slug);
});

it('returns not found for an unknown domain', function () {
    $this->getJson('/api/v1/public/schools/resolve-domain?domain=unknown-domain.ng')
        ->assertNotFound();
});

it('requires the domain query parameter', function () {
    $this->getJson('/api/v1/public/schools/resolve-domain')
        ->assertStatus(422);
});

it('rejects the internal activate endpoint without the shared secret', function () {
    $school = School::factory()->create(['activated' => false]);

    $this->postJson("/api/v1/internal/schools/{$school->id}/activate")
        ->assertStatus(401);
});

it('activates a school when the shared secret is correct', function () {
    config(['services.internal_shared_secret' => 'test-secret']);
    $school = School::factory()->create(['activated' => false]);

    $this->postJson(
        "/api/v1/internal/schools/{$school->id}/activate",
        [],
        ['X-Internal-Secret' => 'test-secret']
    )->assertOk();

    expect($school->fresh()->activated)->toBeTrue();
});
