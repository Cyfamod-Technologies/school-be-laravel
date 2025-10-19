<?php

use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermSummary;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->school = School::factory()->create();

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'admin',
        'status' => 'active',
    ]);

    Sanctum::actingAs($this->user, [], 'sanctum');

    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2024/2025 Session',
        'slug' => '2024-2025-session',
        'start_date' => now()->subMonths(6),
        'end_date' => now()->addMonths(6),
        'status' => 'active',
    ]);

    $this->term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'First Term',
        'slug' => 'first-term',
        'start_date' => now()->subMonths(2),
        'end_date' => now()->addMonths(1),
        'status' => 'active',
    ]);

    $this->class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'JSS 3',
        'slug' => 'jss-3',
    ]);

    $this->classArm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'Arm A',
        'slug' => 'arm-a',
    ]);

    $this->parentUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'parent',
        'status' => 'active',
    ]);

    $this->parent = SchoolParent::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $this->parentUser->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $this->student = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => '2024/001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'M',
        'date_of_birth' => now()->subYears(10),
        'house' => 'Blue',
        'club' => 'Music',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->classArm->id,
        'parent_id' => $this->parent->id,
        'class_section_id' => null,
        'admission_date' => now()->subYears(2),
        'status' => 'active',
    ]);

    $this->termSummary = TermSummary::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'total_marks_obtained' => 450,
        'total_marks_possible' => 500,
        'average_score' => 90,
        'position_in_class' => 1,
        'class_average_score' => 78.5,
        'days_present' => 45,
        'days_absent' => 2,
        'final_grade' => 'A',
        'overall_comment' => 'Hardworking student.',
        'principal_comment' => 'Keep up the great work.',
    ]);
});

it('retrieves class teacher and principal comments', function () {
    getJson(route('students.term-summary.show', [
        'student' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]))
        ->assertOk()
        ->assertJsonPath('data.class_teacher_comment', 'Hardworking student.')
        ->assertJsonPath('data.principal_comment', 'Keep up the great work.');
});

it('updates class teacher and principal comments', function () {
    putJson(route('students.term-summary.update', [
        'student' => $this->student->id,
    ]), [
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'class_teacher_comment' => 'Shows remarkable improvement.',
        'principal_comment' => 'Excellent overall performance.',
    ])
        ->assertOk()
        ->assertJsonPath('data.class_teacher_comment', 'Shows remarkable improvement.')
        ->assertJsonPath('data.principal_comment', 'Excellent overall performance.');

    $this->termSummary->refresh();

    expect($this->termSummary->overall_comment)->toBe('Shows remarkable improvement.')
        ->and($this->termSummary->principal_comment)->toBe('Excellent overall performance.');
});

it('creates term summary when missing', function () {
    $newTerm = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'Second Term',
        'slug' => 'second-term',
        'start_date' => now()->addMonths(1),
        'end_date' => now()->addMonths(4),
        'status' => 'planned',
    ]);

    putJson(route('students.term-summary.update', [
        'student' => $this->student->id,
    ]), [
        'session_id' => $this->session->id,
        'term_id' => $newTerm->id,
        'class_teacher_comment' => 'Great participation.',
        'principal_comment' => 'Keep striving for excellence.',
    ])
        ->assertOk()
        ->assertJsonPath('data.class_teacher_comment', 'Great participation.')
        ->assertJsonPath('data.principal_comment', 'Keep striving for excellence.');

    $createdSummary = TermSummary::query()
        ->where('student_id', $this->student->id)
        ->where('session_id', $this->session->id)
        ->where('term_id', $newTerm->id)
        ->first();

    expect($createdSummary)->not->toBeNull()
        ->and($createdSummary->overall_comment)->toBe('Great participation.')
        ->and($createdSummary->principal_comment)->toBe('Keep striving for excellence.');
});
