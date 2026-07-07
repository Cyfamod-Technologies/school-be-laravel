<?php

use App\Models\SchoolWebsite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();
});

afterEach(function () {
    Schema::enableForeignKeyConstraints();
});

function actingAsSchoolWebsiteUser(
    ?string $schoolId
): User {
    $user = new User;

    $user->forceFill([
        'id' => (string) Str::uuid(),
        'school_id' => $schoolId,
        'email' => Str::uuid().'@example.test',
    ]);

    Sanctum::actingAs($user);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function validSchoolWebsitePayload(
    array $overrides = []
): array {
    $payload = [
        'contractVersion' => 1,
        'status' => 'draft',
        'themeKey' => 'kidza-home-2',

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
    ];

    return array_replace_recursive(
        $payload,
        $overrides
    );
}

it('rejects unauthenticated website management requests', function () {
    $this
        ->putJson(
            '/api/v1/school/website',
            validSchoolWebsitePayload()
        )
        ->assertUnauthorized();
});

it('rejects an authenticated user without a school', function () {
    actingAsSchoolWebsiteUser(null);

    $this
        ->putJson(
            '/api/v1/school/website',
            validSchoolWebsitePayload()
        )
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Authenticated user is not associated with a school.'
        );
});

it('validates the website management payload', function () {
    actingAsSchoolWebsiteUser(
        (string) Str::uuid()
    );

    $payload = validSchoolWebsitePayload([
        'status' => 'live',
        'themeKey' => 'unsupported-theme',

        'branding' => [
            'primaryColor' => 'blue',
        ],

        'contact' => [
            'email' => 'not-an-email',
        ],

        'socialLinks' => [
            'facebook' => 'not-a-url',
        ],
    ]);

    $this
        ->putJson(
            '/api/v1/school/website',
            $payload
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'status',
            'themeKey',
            'branding.primaryColor',
            'contact.email',
            'socialLinks.facebook',
        ]);

    expect(SchoolWebsite::query()->count())
        ->toBe(0);
});

it('creates website settings for the authenticated school', function () {
    $schoolId = (string) Str::uuid();

    actingAsSchoolWebsiteUser($schoolId);

    $this
        ->putJson(
            '/api/v1/school/website',
            validSchoolWebsitePayload()
        )
        ->assertOk()
        ->assertJsonPath(
            'data.schoolId',
            $schoolId
        )
        ->assertJsonPath(
            'data.contractVersion',
            1
        )
        ->assertJsonPath(
            'data.status',
            'draft'
        )
        ->assertJsonPath(
            'data.themeKey',
            'kidza-home-2'
        )
        ->assertJsonPath(
            'data.branding.primaryColor',
            '#2563eb'
        );

    $this->assertDatabaseHas(
        'school_websites',
        [
            'school_id' => $schoolId,
            'status' => 'draft',
            'theme_key' => 'kidza-home-2',
        ]
    );
});

it('retrieves the authenticated school website', function () {
    $schoolId = (string) Str::uuid();

    actingAsSchoolWebsiteUser($schoolId);

    $this->putJson(
        '/api/v1/school/website',
        validSchoolWebsitePayload()
    )->assertOk();

    $this
        ->getJson('/api/v1/school/website')
        ->assertOk()
        ->assertJsonPath(
            'data.schoolId',
            $schoolId
        )
        ->assertJsonPath(
            'data.header.tagline',
            'Learn • Grow • Succeed'
        );
});

it('returns not found when the school has no website', function () {
    actingAsSchoolWebsiteUser(
        (string) Str::uuid()
    );

    $this
        ->getJson('/api/v1/school/website')
        ->assertNotFound();
});

it('updates one existing website instead of creating another', function () {
    $schoolId = (string) Str::uuid();

    actingAsSchoolWebsiteUser($schoolId);

    $this->putJson(
        '/api/v1/school/website',
        validSchoolWebsitePayload()
    )->assertOk();

    $updatedPayload = validSchoolWebsitePayload([
        'status' => 'published',

        'hero' => [
            'title' => 'An updated website title',
        ],
    ]);

    $this
        ->putJson(
            '/api/v1/school/website',
            $updatedPayload
        )
        ->assertOk()
        ->assertJsonPath(
            'data.status',
            'published'
        )
        ->assertJsonPath(
            'data.hero.title',
            'An updated website title'
        );

    $website = SchoolWebsite::query()
        ->where('school_id', $schoolId)
        ->firstOrFail();

    expect(
        SchoolWebsite::query()
            ->where('school_id', $schoolId)
            ->count()
    )
        ->toBe(1)
        ->and($website->hero['title'])
        ->toBe('An updated website title')
        ->and($website->published_at)
        ->not->toBeNull();
});

it('clears the publication timestamp when returned to draft', function () {
    $schoolId = (string) Str::uuid();

    actingAsSchoolWebsiteUser($schoolId);

    $this->putJson(
        '/api/v1/school/website',
        validSchoolWebsitePayload([
            'status' => 'published',
        ])
    )->assertOk();

    expect(
        SchoolWebsite::query()
            ->where('school_id', $schoolId)
            ->firstOrFail()
            ->published_at
    )->not->toBeNull();

    $this->putJson(
        '/api/v1/school/website',
        validSchoolWebsitePayload([
            'status' => 'draft',
        ])
    )->assertOk();

    expect(
        SchoolWebsite::query()
            ->where('school_id', $schoolId)
            ->firstOrFail()
            ->published_at
    )->toBeNull();
});

it('isolates website settings between schools', function () {
    $schoolAId = (string) Str::uuid();
    $schoolBId = (string) Str::uuid();

    actingAsSchoolWebsiteUser($schoolBId);

    $this->putJson(
        '/api/v1/school/website',
        validSchoolWebsitePayload([
            'hero' => [
                'title' => 'School B website',
            ],
        ])
    )->assertOk();

    actingAsSchoolWebsiteUser($schoolAId);

    $this
        ->getJson('/api/v1/school/website')
        ->assertNotFound();

    $this->putJson(
        '/api/v1/school/website',
        validSchoolWebsitePayload([
            'hero' => [
                'title' => 'School A website',
            ],
        ])
    )->assertOk();

    expect(SchoolWebsite::query()->count())
        ->toBe(2)
        ->and(
            SchoolWebsite::query()
                ->where('school_id', $schoolAId)
                ->firstOrFail()
                ->hero['title']
        )
        ->toBe('School A website')
        ->and(
            SchoolWebsite::query()
                ->where('school_id', $schoolBId)
                ->firstOrFail()
                ->hero['title']
        )
        ->toBe('School B website');
});
