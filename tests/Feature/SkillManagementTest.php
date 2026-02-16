<?php

use App\Models\School;
use App\Models\SkillCategory;
use App\Models\SkillType;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->school = School::factory()->create();

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'admin',
        'status' => 'active',
    ]);

    Sanctum::actingAs($this->user, [], 'sanctum');

    $this->category = SkillCategory::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Behaviour',
        'description' => 'Non academic behaviour traits',
    ]);

    $this->skillType = SkillType::create([
        'id' => (string) Str::uuid(),
        'skill_category_id' => $this->category->id,
        'school_id' => $this->school->id,
        'name' => 'Punctuality',
        'description' => 'Arrives on time',
        'weight' => 1.00,
    ]);
});

it('lists skill categories with their skills', function () {
    getJson(route('skill-categories.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Behaviour')
        ->assertJsonPath('data.0.skill_types.0.name', 'Punctuality');
});

it('creates a skill category', function () {
    postJson(route('skill-categories.store'), [
        'name' => 'Attitude',
        'description' => 'General attitude in class',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Attitude');

    expect(SkillCategory::where('name', 'Attitude')->where('school_id', $this->school->id)->exists())->toBeTrue();
});

it('updates a skill category', function () {
    putJson(route('skill-categories.update', $this->category->id), [
        'name' => 'Behaviour & Conduct',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Behaviour & Conduct');
});

it('deletes a skill category', function () {
    deleteJson(route('skill-categories.destroy', $this->category->id))
        ->assertOk();

    expect(SkillCategory::where('id', $this->category->id)->exists())->toBeFalse();
});

it('lists skill types with category names', function () {
    getJson(route('skill-types.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Punctuality')
        ->assertJsonPath('data.0.category', 'Behaviour');
});

it('filters skill types by selected category', function () {
    $secondCategory = SkillCategory::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Affective',
        'description' => 'Affective skills',
    ]);

    SkillType::create([
        'id' => (string) Str::uuid(),
        'skill_category_id' => $secondCategory->id,
        'school_id' => $this->school->id,
        'name' => 'Empathy',
        'description' => 'Shows empathy',
        'weight' => 2.00,
    ]);

    getJson(route('skill-types.index', ['skill_category_id' => $this->category->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Punctuality')
        ->assertJsonPath('data.0.category', 'Behaviour');
});

it('creates a skill type', function () {
    postJson(route('skill-types.store'), [
        'skill_category_id' => $this->category->id,
        'name' => 'Neatness',
        'weight' => 2,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Neatness');

    expect(SkillType::where('name', 'Neatness')->where('school_id', $this->school->id)->exists())->toBeTrue();
});

it('creates a skill type with a long name', function () {
    $longName = trim(str_repeat('Very descriptive behaviour skill ', 12));

    postJson(route('skill-types.store'), [
        'skill_category_id' => $this->category->id,
        'name' => $longName,
        'weight' => 2,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', $longName);

    expect(SkillType::where('name', $longName)->where('school_id', $this->school->id)->exists())->toBeTrue();
});

it('creates multiple skill types in one request', function () {
    postJson(route('skill-types.bulk-store'), [
        'skill_category_id' => $this->category->id,
        'names' => [
            'Neatness',
            'Communication',
            'Leadership',
        ],
    ])
        ->assertCreated()
        ->assertJsonCount(3, 'data');

    expect(SkillType::where('school_id', $this->school->id)->count())->toBe(4);
    expect(SkillType::where('name', 'Communication')->where('school_id', $this->school->id)->exists())->toBeTrue();
});

it('updates a skill type', function () {
    putJson(route('skill-types.update', $this->skillType->id), [
        'description' => 'Consistently punctual',
        'weight' => 3,
    ])
        ->assertOk()
        ->assertJsonPath('data.description', 'Consistently punctual')
        ->assertJsonPath('data.weight', 3);
});

it('deletes a skill type', function () {
    deleteJson(route('skill-types.destroy', $this->skillType->id))
        ->assertOk();

    expect(SkillType::where('id', $this->skillType->id)->exists())->toBeFalse();
});
