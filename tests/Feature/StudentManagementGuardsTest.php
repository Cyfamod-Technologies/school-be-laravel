<?php

use App\Models\ClassArm;
use App\Models\ClassTeacher;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectTeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
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

it('regenerates admission numbers only for selected students', function () {
    $secondStudent = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => 'ADM-1002',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
        'date_of_birth' => Carbon::parse('2014-04-20'),
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'admission_date' => Carbon::parse('2023-09-01'),
        'status' => 'active',
    ]);

    $oldFirstAdmissionNo = $this->student->admission_no;
    $oldSecondAdmissionNo = $secondStudent->admission_no;

    postJson(route('students.admission-numbers.regenerate'), [
        'student_ids' => [$this->student->id],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->student->id)
        ->assertJsonPath('data.0.previous_admission_no', $oldFirstAdmissionNo);

    expect($this->student->fresh()->admission_no)->not()->toBe($oldFirstAdmissionNo)
        ->and($secondStudent->fresh()->admission_no)->toBe($oldSecondAdmissionNo);
});

it('does not regenerate admission numbers for students from another school', function () {
    $otherSchool = School::factory()->create(['status' => 'active']);
    $otherStudent = $this->student->replicate();
    $otherStudent->id = (string) Str::uuid();
    $otherStudent->school_id = $otherSchool->id;
    $otherStudent->admission_no = 'OTHER-1001';
    $otherStudent->save();

    postJson(route('students.admission-numbers.regenerate'), [
        'student_ids' => [$otherStudent->id],
    ])->assertUnprocessable();

    expect($otherStudent->fresh()->admission_no)->toBe('OTHER-1001');
});

it('does not allow non-admin users to regenerate admission numbers', function () {
    $staffUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'staff',
        'status' => 'active',
    ]);
    Sanctum::actingAs($staffUser, [], 'sanctum');

    postJson(route('students.admission-numbers.regenerate'), [
        'student_ids' => [$this->student->id],
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only administrators can regenerate admission numbers.');

    expect($this->student->fresh()->admission_no)->toBe('ADM-1001');
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

it('allows teachers to create students', function () {
    $teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'teacher',
        'status' => 'active',
    ]);

    Staff::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $teacherUser->id,
        'full_name' => 'Mrs Teacher',
        'email' => 'teacher@example.com',
        'phone' => '08000000000',
        'role' => 'Class Teacher',
        'gender' => 'female',
        'employment_start_date' => Carbon::parse('2024-01-01'),
    ]);

    Sanctum::actingAs($teacherUser, [], 'sanctum');

    postJson(route('students.store'), [
        'admission_no' => 'ADM-TEACHER-001',
        'first_name' => 'Teacher',
        'last_name' => 'Created',
        'gender' => 'female',
        'date_of_birth' => '2013-01-01',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'admission_date' => '2025-09-01',
        'status' => 'active',
    ])
        ->assertCreated()
        ->assertJsonPath('data.first_name', 'Teacher')
        ->assertJsonPath('data.school_id', $this->school->id);
});

it('prevents subject teachers from opening student records without class teacher assignment', function () {
    $teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'teacher',
        'status' => 'active',
    ]);

    $staff = Staff::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $teacherUser->id,
        'full_name' => 'Subject Teacher',
        'email' => 'subject.teacher.guard@example.test',
        'phone' => '08020000000',
        'role' => 'Subject Teacher',
        'gender' => 'male',
    ]);

    $subject = Subject::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Mathematics',
        'code' => 'MTH',
    ]);

    SubjectTeacherAssignment::create([
        'id' => (string) Str::uuid(),
        'subject_id' => $subject->id,
        'staff_id' => $staff->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'class_section_id' => null,
        'student_ids' => null,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]);

    Sanctum::actingAs($teacherUser, [], 'sanctum');

    getJson(route('students.index'))
        ->assertOk()
        ->assertJsonFragment(['id' => $this->student->id]);

    getJson(route('students.show', $this->student->id))
        ->assertForbidden();
});

it('allows an assigned class teacher to open student details without a separate students view permission', function () {
    $teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'teacher',
        'status' => 'active',
    ]);

    $staff = Staff::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $teacherUser->id,
        'full_name' => 'Assigned Class Teacher',
        'email' => 'class.teacher.guard@example.test',
        'phone' => '08030000000',
        'role' => 'Class Teacher',
        'gender' => 'female',
    ]);

    ClassTeacher::create([
        'id' => (string) Str::uuid(),
        'staff_id' => $staff->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'class_section_id' => null,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]);

    Sanctum::actingAs($teacherUser, [], 'sanctum');

    getJson(route('students.show', $this->student->id))
        ->assertOk()
        ->assertJsonPath('data.id', $this->student->id);
});

it('resolves legacy students before repairing invalid foreign keys', function () {
    // students.class_section_id is a MariaDB-native UUID column (MariaDB
    // 10.7+), which validates format at the storage-engine level -- there
    // is no write path, not even with FOREIGN_KEY_CHECKS disabled, that
    // can put a literal '0' in it anymore. fixLegacyForeignKeys() exists
    // to repair rows that were corrupted before this column had that
    // native type; that historical state can no longer be reproduced by
    // writing through SQL, so this scenario can't be simulated as a
    // regression test under the current schema.
    $this->markTestSkipped(
        'class_section_id is now a MariaDB-native UUID column; a malformed '.
        "value like '0' can no longer be written through any SQL path to ".
        'simulate the legacy-corruption scenario this guards against.'
    );

    DB::table('students')
        ->where('id', $this->student->id)
        ->update(['class_section_id' => '0']);

    expect(Student::query()->whereKey($this->student->id)->exists())->toBeFalse();

    getJson(route('students.show', $this->student->id))
        ->assertOk()
        ->assertJsonPath('data.id', $this->student->id)
        ->assertJsonPath('data.class_section_id', null);

    expect(DB::table('students')->where('id', $this->student->id)->value('class_section_id'))
        ->toBeNull();
});
