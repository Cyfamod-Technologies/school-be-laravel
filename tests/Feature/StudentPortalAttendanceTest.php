<?php

use App\Models\Attendance;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Carbon::setTestNow('2026-08-10 09:00:00');

    $this->school = School::factory()->create();
    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'start_date' => '2025-09-01',
        'end_date' => '2026-08-31',
        'status' => 'active',
    ]);
    $this->term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'Third Term',
        'term_number' => 3,
        'slug' => 'third-term',
        'start_date' => '2026-04-20',
        'end_date' => '2026-08-14',
        'status' => 'active',
    ]);
    $this->school->update([
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
    ]);

    $this->class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'JSS 1',
        'slug' => 'jss-1',
    ]);
    $this->arm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'A',
        'slug' => 'a',
    ]);
    $this->student = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => 'ADM-001',
        'first_name' => 'Ada',
        'last_name' => 'Obi',
        'gender' => 'Female',
        'date_of_birth' => '2014-05-16',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'admission_date' => '2020-09-10',
        'status' => 'active',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns only the authenticated student attendance with term and month summaries', function () {
    $otherStudent = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => 'ADM-002',
        'first_name' => 'Tayo',
        'last_name' => 'Cole',
        'gender' => 'Male',
        'date_of_birth' => '2014-06-16',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'admission_date' => '2020-09-10',
        'status' => 'active',
    ]);

    foreach ([
        ['student' => $this->student, 'date' => '2026-08-03', 'status' => 'present'],
        ['student' => $this->student, 'date' => '2026-08-04', 'status' => 'absent'],
        ['student' => $this->student, 'date' => '2026-07-30', 'status' => 'late'],
        ['student' => $otherStudent, 'date' => '2026-08-03', 'status' => 'absent'],
    ] as $entry) {
        Attendance::create([
            'id' => (string) Str::uuid(),
            'student_id' => $entry['student']->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'school_class_id' => $this->class->id,
            'class_arm_id' => $this->arm->id,
            'date' => $entry['date'],
            'status' => $entry['status'],
        ]);
    }

    Sanctum::actingAs($this->student, [], 'student');

    getJson(route('student.attendance.index', [
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'month' => '2026-08',
    ]))
        ->assertOk()
        ->assertJsonPath('summary.present', 1)
        ->assertJsonPath('summary.absent', 1)
        ->assertJsonPath('summary.late', 1)
        ->assertJsonPath('summary.recorded_days', 3)
        ->assertJsonCount(2, 'days')
        ->assertJsonPath('days.0.date', '2026-08-03')
        ->assertJsonPath('days.1.status', 'absent');
});

it('prevents a subject teacher from recording daily class attendance', function () {
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
        'email' => 'subject@example.test',
        'phone' => '08010000001',
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
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
    ]);

    Sanctum::actingAs($teacherUser, [], 'sanctum');

    postJson(route('attendance.students.store'), [
        'date' => '2026-08-10',
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'entries' => [[
            'student_id' => $this->student->id,
            'status' => 'present',
        ]],
    ])->assertForbidden();
});

it('allows the assigned class teacher to autosave current-term attendance', function () {
    $teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'teacher',
        'status' => 'active',
    ]);
    $staff = Staff::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $teacherUser->id,
        'full_name' => 'Class Teacher',
        'email' => 'class@example.test',
        'phone' => '08010000002',
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

    postJson(route('attendance.students.store'), [
        'date' => '2026-08-10',
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'school_class_id' => (string) Str::uuid(),
        'entries' => [[
            'student_id' => $this->student->id,
            'status' => 'present',
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('created', 1);

    $attendance = Attendance::query()->where('student_id', $this->student->id)->firstOrFail();

    expect($attendance->school_class_id)->toBe($this->class->id)
        ->and($attendance->class_arm_id)->toBe($this->arm->id)
        ->and($attendance->recorded_by)->toBe($teacherUser->id);
});

it('uses the configured current term even when its calendar dates are stale', function () {
    $this->term->update([
        'start_date' => '2025-01-01',
        'end_date' => '2025-04-30',
    ]);

    $teacherUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'teacher',
        'status' => 'active',
    ]);
    $staff = Staff::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $teacherUser->id,
        'full_name' => 'Class Teacher',
        'email' => 'stale-term@example.test',
        'phone' => '08010000003',
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

    postJson(route('attendance.students.store'), [
        'date' => '2026-08-10',
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'entries' => [[
            'student_id' => $this->student->id,
            'status' => 'present',
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('created', 1);
});
