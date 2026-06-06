<?php

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\SubjectTeacherAssignment;
use App\Models\Term;
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
        'name' => 'Primary 3',
        'slug' => 'primary-3',
    ]);

    $this->teacher = Staff::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'staff_no' => 'STF-1001',
        'full_name' => 'Nursery Teacher',
        'email' => 'teacher@example.com',
        'phone' => '08000000000',
        'gender' => 'female',
        'role' => 'teacher',
        'status' => 'active',
        'address' => 'School compound',
    ]);

    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2026/2027',
    ]);

    $this->term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'First Term',
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

it('creates multiple teacher subject assignments in one request', function () {
    postJson('/api/v1/settings/subject-teacher-assignments', [
        'subject_ids' => [
            $this->subjectA->id,
            $this->subjectB->id,
        ],
        'staff_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ])
        ->assertCreated()
        ->assertJsonPath('created_count', 2)
        ->assertJsonPath('skipped_count', 0)
        ->assertJsonCount(2, 'data');

    expect(
        SubjectTeacherAssignment::query()
            ->where('staff_id', $this->teacher->id)
            ->where('school_class_id', $this->class->id)
            ->count()
    )->toBe(2);
});

it('skips duplicate teacher subject assignments during bulk create', function () {
    SubjectTeacherAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->subjectA->id,
        'staff_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => null,
        'class_section_id' => null,
        'student_ids' => null,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]);

    postJson('/api/v1/settings/subject-teacher-assignments', [
        'subject_ids' => [
            $this->subjectA->id,
            $this->subjectB->id,
            $this->subjectC->id,
        ],
        'staff_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ])
        ->assertCreated()
        ->assertJsonPath('created_count', 2)
        ->assertJsonPath('skipped_count', 1)
        ->assertJsonPath('skipped_subject_ids.0', $this->subjectA->id);

    expect(
        SubjectTeacherAssignment::query()
            ->where('staff_id', $this->teacher->id)
            ->where('school_class_id', $this->class->id)
            ->count()
    )->toBe(3);
});
