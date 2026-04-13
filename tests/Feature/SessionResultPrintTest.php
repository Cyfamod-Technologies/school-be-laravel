<?php

use App\Models\ClassArm;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->school = School::factory()->create([
        'owner_name' => 'Mrs Director',
        'result_signatory_title' => 'director',
        'result_enable_session_print' => true,
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

    $this->terms = collect([
        ['name' => '1st Term', 'term_number' => 1],
        ['name' => '2nd Term', 'term_number' => 2],
        ['name' => '3rd Term', 'term_number' => 3],
    ])->map(function (array $termData) {
        return Term::create([
            'id' => (string) Str::uuid(),
            'school_id' => $this->school->id,
            'session_id' => $this->session->id,
            'name' => $termData['name'],
            'term_number' => $termData['term_number'],
            'slug' => Str::slug($termData['name']),
            'start_date' => now()->subMonths(5 - $termData['term_number']),
            'end_date' => now()->subMonths(4 - $termData['term_number']),
            'status' => 'active',
        ]);
    });

    $this->class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'JSS 1',
        'slug' => 'jss-1',
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
        'admission_no' => 'JSS-001',
        'first_name' => 'Amina',
        'last_name' => 'Yusuf',
        'gender' => 'F',
        'date_of_birth' => now()->subYears(12),
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->terms->last()->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->classArm->id,
        'admission_date' => now()->subYear(),
        'status' => 'active',
    ]);

    $this->subject = Subject::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Mathematics',
        'code' => 'MTH',
    ]);

    foreach ([75, 80, 88] as $index => $score) {
        Result::create([
            'id' => (string) Str::uuid(),
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'assessment_component_id' => null,
            'session_id' => $this->session->id,
            'term_id' => $this->terms[$index]->id,
            'total_score' => $score,
            'remarks' => 'Pass',
        ]);
    }
});

it('renders the session result print layout without affecting term result printing', function () {
    get("/api/v1/results/session/print?session_id={$this->session->id}&school_class_id={$this->class->id}")
        ->assertOk()
        ->assertSeeText('Session Result Sheet')
        ->assertSeeText('2025/2026 Session')
        ->assertSeeText('Mathematics')
        ->assertSeeText('1st Term')
        ->assertSeeText('2nd Term')
        ->assertSeeText('3rd Term')
        ->assertSeeText('243')
        ->assertSeeText('81.00');
});
