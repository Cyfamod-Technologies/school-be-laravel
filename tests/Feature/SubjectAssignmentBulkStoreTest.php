<?php

use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->school = School::factory()->create();

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'admin',
        'status' => 'active',
    ]);

    Sanctum::actingAs($this->user, [], 'sanctum');

    $this->class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'JSS 1',
        'slug' => 'jss-1',
    ]);

    $this->arm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'A',
        'slug' => 'a',
    ]);

    $this->subjectA = Subject::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Mathematics',
        'code' => 'MTH',
    ]);

    $this->subjectB = Subject::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'English Language',
        'code' => 'ENG',
    ]);

    $this->subjectC = Subject::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Basic Science',
        'code' => 'BSC',
    ]);
});

it('creates multiple subject assignments for the same class arm in one request', function () {
    postJson('/api/v1/settings/subject-assignments', [
        'subject_ids' => [
            $this->subjectA->id,
            $this->subjectB->id,
        ],
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
    ])
        ->assertCreated()
        ->assertJsonPath('created_count', 2)
        ->assertJsonPath('skipped_count', 0)
        ->assertJsonCount(2, 'data');

    expect(
        SubjectAssignment::query()
            ->where('school_class_id', $this->class->id)
            ->where('class_arm_id', $this->arm->id)
            ->count()
    )->toBe(2);
});

it('skips duplicate subject assignments during bulk create', function () {
    SubjectAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->subjectA->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'class_section_id' => null,
    ]);

    postJson('/api/v1/settings/subject-assignments', [
        'subject_ids' => [
            $this->subjectA->id,
            $this->subjectB->id,
            $this->subjectC->id,
        ],
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
    ])
        ->assertCreated()
        ->assertJsonPath('created_count', 2)
        ->assertJsonPath('skipped_count', 1)
        ->assertJsonPath('skipped_subject_ids.0', $this->subjectA->id);

    expect(
        SubjectAssignment::query()
            ->where('school_class_id', $this->class->id)
            ->where('class_arm_id', $this->arm->id)
            ->count()
    )->toBe(3);
});
