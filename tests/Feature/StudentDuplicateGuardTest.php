<?php

use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
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

    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'start_date' => Carbon::parse('2025-09-01'),
        'end_date' => Carbon::parse('2026-07-31'),
        'status' => 'active',
    ]);

    $this->term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'First Term',
        'slug' => 'first-term',
        'start_date' => Carbon::parse('2025-09-01'),
        'end_date' => Carbon::parse('2025-12-15'),
        'status' => 'active',
    ]);

    $this->class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Grade 5',
        'slug' => 'grade-5',
    ]);

    $this->arm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'Arm A',
        'slug' => 'arm-a',
    ]);
});

it('flags duplicate students by first and last name when creating', function () {
    $existing = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => 'ADM-001',
        'first_name' => 'Ada',
        'last_name' => 'Obi',
        'gender' => 'female',
        'date_of_birth' => Carbon::parse('2014-05-16'),
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'class_section_id' => null,
        'admission_date' => Carbon::parse('2020-09-10'),
        'status' => 'active',
    ]);

    postJson(route('students.store'), [
        'admission_no' => 'ADM-002',
        'first_name' => 'ada',
        'last_name' => 'OBI',
        'gender' => 'female',
        'date_of_birth' => '2012-01-01',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'admission_date' => '2020-09-10',
        'status' => 'active',
    ])->assertStatus(409)
        ->assertJsonPath('is_duplicate', true)
        ->assertJsonPath('duplicate.id', $existing->id)
        ->assertJsonPath('duplicate.match', 'name');

    expect(Student::where('school_id', $this->school->id)->count())->toBe(1);
});
