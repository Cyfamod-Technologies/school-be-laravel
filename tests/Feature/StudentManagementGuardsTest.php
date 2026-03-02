<?php

use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->school = School::factory()->create([
        'status' => 'active',
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
        'name' => 'Grade 4',
        'slug' => 'grade-4',
    ]);

    $this->arm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'Arm A',
        'slug' => 'arm-a',
    ]);

    $this->student = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => 'ADM-1001',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'gender' => 'female',
        'date_of_birth' => Carbon::parse('2014-03-20'),
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'class_section_id' => null,
        'admission_date' => Carbon::parse('2023-09-01'),
        'status' => 'active',
    ]);
});

it('allows clearing class arm by sending none in update payload', function () {
    putJson(route('students.update', $this->student->id), [
        'first_name' => $this->student->first_name,
        'middle_name' => $this->student->middle_name,
        'last_name' => $this->student->last_name,
        'gender' => 'female',
        'date_of_birth' => $this->student->date_of_birth->toDateString(),
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => 'none',
        'admission_date' => $this->student->admission_date->toDateString(),
        'status' => 'active',
    ])
        ->assertOk()
        ->assertJsonPath('data.class_arm_id', null);

    expect($this->student->fresh()->class_arm_id)->toBeNull();
});

it('prevents deleting a student with dependent records', function () {
    DB::table('attendances')->insert([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'date' => Carbon::parse('2025-09-10')->toDateString(),
        'status' => 'present',
    ]);

    deleteJson(route('students.destroy', $this->student->id))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Cannot delete student with dependent records. Remove related records first.')
        ->assertJsonFragment(['attendance records']);

    expect(Student::where('id', $this->student->id)->exists())->toBeTrue();
});
