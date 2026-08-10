<?php

use App\Models\ClassArm;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\SubjectTeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->school = School::factory()->create();

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'admin',
        'status' => 'active',
    ]);

    $this->nonAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'staff',
        'status' => 'active',
    ]);

    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'start_date' => now()->startOfYear(),
        'end_date' => now()->endOfYear(),
        'status' => 'active',
    ]);

    $this->term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'First Term',
        'slug' => 'first-term',
        'start_date' => now()->startOfYear(),
        'end_date' => now()->addMonths(3),
        'status' => 'active',
    ]);

    $this->classA = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Grade 6',
        'slug' => 'grade-6',
    ]);

    $this->classB = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Grade 5',
        'slug' => 'grade-5',
    ]);

    $this->armA = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->classA->id,
        'name' => 'Arm A',
        'slug' => 'arm-a',
    ]);

    $this->math = Subject::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Mathematics',
    ]);

    $this->english = Subject::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'English',
    ]);

    SubjectAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->math->id,
        'session_id' => $this->session->id,
        'school_class_id' => $this->classA->id,
    ]);

    SubjectAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->english->id,
        'session_id' => $this->session->id,
        'school_class_id' => $this->classA->id,
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
        'first_name' => 'Parent',
        'last_name' => 'One',
    ]);

    $this->staff = Staff::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $this->nonAdmin->id,
        'full_name' => 'Teacher One',
        'email' => 'teacher@example.test',
        'phone' => '08000000000',
        'role' => 'Teacher',
        'gender' => 'male',
    ]);

    SubjectTeacherAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $this->math->id,
        'staff_id' => $this->staff->id,
        'school_class_id' => $this->classA->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]);

    $armB = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->classB->id,
        'name' => 'Arm B',
        'slug' => 'arm-b',
    ]);

    $this->studentHigh = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => 'ADM-001',
        'first_name' => 'Ada',
        'last_name' => 'Okafor',
        'gender' => 'Female',
        'date_of_birth' => now()->subYears(12),
        'house' => 'Blue',
        'club' => 'Science',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->classA->id,
        'class_arm_id' => $this->armA->id,
        'class_section_id' => null,
        'parent_id' => $this->parent->id,
        'admission_date' => now()->subYears(6),
        'status' => 'active',
    ]);

    $this->studentLow = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => 'ADM-002',
        'first_name' => 'Bola',
        'last_name' => 'Adewale',
        'gender' => 'Male',
        'date_of_birth' => now()->subYears(13),
        'house' => 'Red',
        'club' => 'Music',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->classB->id,
        'class_arm_id' => $armB->id,
        'class_section_id' => null,
        'parent_id' => $this->parent->id,
        'admission_date' => now()->subYears(6),
        'status' => 'active',
    ]);

    Result::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->studentHigh->id,
        'subject_id' => $this->math->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'total_score' => 85,
    ]);

    Result::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->studentHigh->id,
        'subject_id' => $this->english->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'total_score' => 75,
    ]);

    Result::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->studentLow->id,
        'subject_id' => $this->math->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'total_score' => 40,
    ]);
});

it('rejects non admin users', function () {
    Sanctum::actingAs($this->nonAdmin, [], 'sanctum');

    getJson(route('analytics.academics'))
        ->assertStatus(403);
});

it('returns academic analytics for an admin', function () {
    Sanctum::actingAs($this->admin, [], 'sanctum');

    $response = getJson(route('analytics.academics', [
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]));

    $response->assertOk()
        ->assertJsonStructure([
            'summary' => ['students', 'teachers', 'subjects'],
            'class_metrics' => [
                ['class_id', 'class_name', 'average_score', 'student_count', 'teacher_count', 'subject_count'],
            ],
            'subject_performance' => [
                'top',
                'bottom',
            ],
            'pass_fail' => ['total_students', 'passing_students', 'failing_students', 'pass_rate'],
        ])
        ->assertJsonPath('pass_fail.total_students', 2);
});
