<?php

use App\Models\ClassArm;
use App\Models\Result;
use App\Models\ResultPin;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->school = School::factory()->create();

    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'start_date' => now()->subMonths(4),
        'end_date' => now()->addMonths(4),
        'status' => 'active',
    ]);

    $this->term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'First Term',
        'slug' => 'first-term',
        'start_date' => now()->subMonths(2),
        'end_date' => now()->addMonth(),
        'status' => 'active',
    ]);

    $this->class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Nursery 1',
        'slug' => 'nursery-1',
    ]);

    $this->classArm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'Gurara',
        'slug' => 'gurara',
    ]);

    $parentUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'parent',
        'status' => 'active',
    ]);

    $this->parent = SchoolParent::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $parentUser->id,
        'first_name' => 'Pat',
        'last_name' => 'Guardian',
    ]);

    $this->students = collect(range(1, 2))->map(function (int $index) {
        return Student::create([
            'id' => (string) Str::uuid(),
            'school_id' => $this->school->id,
            'admission_no' => "HIS004-2025/2026/19{$index}",
            'first_name' => "Student{$index}",
            'last_name' => 'Example',
            'gender' => 'M',
            'date_of_birth' => now()->subYears(5 + $index),
            'current_session_id' => $this->session->id,
            'current_term_id' => $this->term->id,
            'school_class_id' => $this->class->id,
            'class_arm_id' => $this->classArm->id,
            'parent_id' => $this->parent->id,
            'admission_date' => now()->subYear(),
            'status' => 'active',
        ]);
    });

    $this->pinOwner = $this->students->first();
    $this->viewer = $this->students->last();

    $this->subject = Subject::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Basic Science',
        'code' => 'BSC',
    ]);

    Result::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->viewer->id,
        'subject_id' => $this->subject->id,
        'assessment_component_id' => null,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'total_score' => 18,
        'remarks' => 'Pass',
    ]);

    $this->pin = ResultPin::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->pinOwner->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'pin_code' => 'ABCD1234',
        'status' => 'active',
        'use_count' => 0,
    ]);
});

it('rejects another students scratch card when shared access is disabled', function () {
    Sanctum::actingAs($this->viewer, [], 'student');

    postJson('/api/v1/student/results/preview', [
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'pin_code' => 'ABCD1234',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pin_code');
});

it('allows another students scratch card when shared access is enabled for the school', function () {
    $this->school->update([
        'result_allow_shared_pin_access' => true,
    ]);

    Sanctum::actingAs($this->viewer, [], 'student');

    postJson('/api/v1/student/results/preview', [
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'pin_code' => 'ABCD 1234',
    ])
        ->assertOk()
        ->assertJsonPath('student.id', $this->viewer->id)
        ->assertJsonPath('results.0.subject', 'Basic Science')
        ->assertJsonPath('results.0.total', 18);

    $this->pin->refresh();

    expect($this->pin->use_count)->toBe(1);
});
