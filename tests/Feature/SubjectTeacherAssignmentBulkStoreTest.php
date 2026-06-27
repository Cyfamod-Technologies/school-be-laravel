<?php

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Staff;
use App\Models\SubjectAssignment;
use App\Models\Subject;
use App\Models\SubjectTeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Models\ClassArm;
use App\Models\ClassTeacher;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
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

    $this->classTwo = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Primary 4',
        'slug' => 'primary-4',
    ]);

    $this->armA = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'A',
        'slug' => 'a',
    ]);

    $this->armB = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'B',
        'slug' => 'b',
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

it('shows assignments on dashboard for staff users with teacher staff role', function () {
    $teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'staff',
        'status' => 'active',
        'email' => 'class.teacher@example.test',
    ]);

    $this->teacher->update([
        'user_id' => $teacherUser->id,
        'role' => 'Class Teacher',
        'email' => 'class.teacher@example.test',
    ]);

    SubjectTeacherAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->subjectA->id,
        'staff_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->armA->id,
        'class_section_id' => null,
        'student_ids' => null,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]);

    Sanctum::actingAs($teacherUser, [], 'sanctum');

    getJson('/api/v1/staff/dashboard')
        ->assertOk()
        ->assertJsonPath('teacher.id', $this->teacher->id)
        ->assertJsonPath('stats.classes', 1)
        ->assertJsonPath('stats.subjects', 1)
        ->assertJsonPath('assignments.0.class.id', $this->class->id)
        ->assertJsonPath('assignments.0.subjects.0.id', $this->subjectA->id);
});

it('shows class teacher assignments on dashboard for staff users with teacher staff role', function () {
    $teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'staff',
        'status' => 'active',
        'email' => 'class.owner@example.test',
    ]);

    $this->teacher->update([
        'user_id' => $teacherUser->id,
        'role' => 'Class Teacher',
        'email' => 'class.owner@example.test',
    ]);

    ClassTeacher::create([
        'id' => (string) Str::uuid(),
        'staff_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->armA->id,
        'class_section_id' => null,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]);

    Sanctum::actingAs($teacherUser, [], 'sanctum');

    getJson('/api/v1/staff/dashboard')
        ->assertOk()
        ->assertJsonPath('teacher.id', $this->teacher->id)
        ->assertJsonPath('stats.classes', 1)
        ->assertJsonPath('assignments.0.class.id', $this->class->id)
        ->assertJsonPath('assignments.0.class_arm.id', $this->armA->id);
});

it('marks only subject teacher subjects as editable when a teacher is also a class teacher', function () {
    $teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'staff',
        'status' => 'active',
        'email' => 'mixed.teacher@example.test',
    ]);

    $this->teacher->update([
        'user_id' => $teacherUser->id,
        'role' => 'Class Teacher',
        'email' => 'mixed.teacher@example.test',
    ]);

    foreach ([$this->subjectA, $this->subjectB] as $subject) {
        SubjectAssignment::create([
            'id' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'school_class_id' => $this->class->id,
            'class_arm_id' => $this->armA->id,
            'class_section_id' => null,
        ]);
    }

    ClassTeacher::create([
        'id' => (string) Str::uuid(),
        'staff_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->armA->id,
        'class_section_id' => null,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]);

    SubjectTeacherAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->subjectA->id,
        'staff_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->armA->id,
        'class_section_id' => null,
        'student_ids' => null,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]);

    Sanctum::actingAs($teacherUser, [], 'sanctum');

    $response = getJson('/api/v1/staff/dashboard')
        ->assertOk()
        ->assertJsonPath('assignments.0.is_class_teacher', true);

    $subjects = collect($response->json('assignments.0.subjects'))->keyBy('id');

    expect($subjects[$this->subjectA->id]['is_subject_teacher'])->toBeTrue()
        ->and($subjects[$this->subjectB->id]['is_subject_teacher'])->toBeFalse();
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

it('creates teacher subject assignments across multiple selected classes in one request', function () {
    postJson('/api/v1/settings/subject-teacher-assignments', [
        'subject_ids' => [
            $this->subjectA->id,
            $this->subjectB->id,
        ],
        'contexts' => [
            ['school_class_id' => $this->class->id],
            ['school_class_id' => $this->classTwo->id],
        ],
        'staff_id' => $this->teacher->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ])
        ->assertCreated()
        ->assertJsonPath('created_count', 4)
        ->assertJsonPath('skipped_count', 0)
        ->assertJsonCount(4, 'data');

    expect(
        SubjectTeacherAssignment::query()
            ->where('staff_id', $this->teacher->id)
            ->count()
    )->toBe(4);
});

it('creates teacher subject assignments across multiple selected class arms in one request', function () {
    postJson('/api/v1/settings/subject-teacher-assignments', [
        'subject_ids' => [
            $this->subjectA->id,
        ],
        'contexts' => [
            [
                'school_class_id' => $this->class->id,
                'class_arm_id' => $this->armA->id,
            ],
            [
                'school_class_id' => $this->class->id,
                'class_arm_id' => $this->armB->id,
            ],
        ],
        'staff_id' => $this->teacher->id,
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
            ->whereNotNull('class_arm_id')
            ->count()
    )->toBe(2);
});
