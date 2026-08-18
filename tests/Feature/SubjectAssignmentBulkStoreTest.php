<?php

use App\Http\Controllers\ResultViewController;
use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'start_date' => '2025-09-01',
        'end_date' => '2026-08-31',
        'status' => 'active',
    ]);
    $this->school->update(['current_session_id' => $this->session->id]);

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
        'session_id' => $this->session->id,
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

it('counts arm-specific subjects without adding class-wide assignments', function () {
    SubjectAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->subjectA->id,
        'session_id' => $this->session->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => null,
        'class_section_id' => null,
    ]);

    foreach ([$this->subjectB, $this->subjectC] as $subject) {
        SubjectAssignment::create([
            'id' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'session_id' => $this->session->id,
            'school_class_id' => $this->class->id,
            'class_arm_id' => $this->arm->id,
            'class_section_id' => null,
        ]);
    }

    $student = new Student;
    $student->school_class_id = $this->class->id;
    $student->class_arm_id = $this->arm->id;
    $student->class_section_id = null;

    $method = new ReflectionMethod(ResultViewController::class, 'resolveSubjectCount');
    $subjectCount = $method->invoke(new ResultViewController, $student, $this->session->id);

    expect($subjectCount)->toBe(2);
});

it('keeps subject assignments independent across sessions', function () {
    SubjectAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->subjectA->id,
        'session_id' => $this->session->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'class_section_id' => null,
    ]);

    $nextSession = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2026/2027',
        'slug' => '2026-2027',
        'start_date' => '2026-09-01',
        'end_date' => '2027-08-31',
        'status' => 'active',
    ]);
    $this->school->update(['current_session_id' => $nextSession->id]);

    postJson('/api/v1/settings/subject-assignments', [
        'subject_id' => $this->subjectA->id,
        'session_id' => $nextSession->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
    ])->assertCreated();

    getJson('/api/v1/settings/subject-assignments?session_id='.$this->session->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.session_id', $this->session->id);

    getJson('/api/v1/settings/subject-assignments?session_id='.$nextSession->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.session_id', $nextSession->id);
});

it('prevents changes to a historical session assignment', function () {
    $assignment = SubjectAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->subjectA->id,
        'session_id' => $this->session->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'class_section_id' => null,
    ]);

    $nextSession = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2026/2027',
        'slug' => '2026-2027',
        'start_date' => '2026-09-01',
        'end_date' => '2027-08-31',
        'status' => 'active',
    ]);
    $this->school->update(['current_session_id' => $nextSession->id]);

    putJson('/api/v1/settings/subject-assignments/'.$assignment->id, [
        'subject_id' => $this->subjectB->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Historical subject assignments are read-only. Switch to the current session to make changes.');
});
