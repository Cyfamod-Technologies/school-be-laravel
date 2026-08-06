<?php

use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermSummary;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->school = School::factory()->create([
        'owner_name' => 'Mrs Director',
        'result_signatory_title' => 'director',
    ]);

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'admin',
        'status' => 'active',
    ]);

    Sanctum::actingAs($this->user, [], 'sanctum');

    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2025/2026 Session',
        'slug' => '2025-2026-session',
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
        'end_date' => now()->addMonth(),
        'status' => 'active',
    ]);

    $this->class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Nursery 2',
        'slug' => 'nursery-2',
    ]);

    $this->classArm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'A',
        'slug' => 'a',
    ]);

    $this->student = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => 'EYR-001',
        'first_name' => 'Ada',
        'last_name' => 'Pupil',
        'gender' => 'F',
        'date_of_birth' => now()->subYears(5),
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->classArm->id,
        'admission_date' => now()->subYear(),
        'status' => 'active',
    ]);

    TermSummary::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'average_score' => 88,
        'overall_comment' => 'Ada is doing well in class.',
        'principal_comment' => 'Excellent progress this term.',
    ]);
});

it('shows the director or principal comment on the early years report', function () {
    get("/api/v1/students/{$this->student->id}/early-years-report/print?session_id={$this->session->id}&term_id={$this->term->id}")
        ->assertOk()
        ->assertSeeText("Director's Comment:")
        ->assertSeeText('Excellent progress this term.')
        ->assertSeeText('Mrs Director');
});
