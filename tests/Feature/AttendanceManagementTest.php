<?php

use App\Models\Attendance;
use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
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

    $this->parentUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'parent',
        'status' => 'active',
    ]);

    $this->parent = SchoolParent::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $this->parentUser->id,
        'first_name' => 'Grace',
        'last_name' => 'Williams',
    ]);

    $this->student = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => 'ADM-001',
        'first_name' => 'Ada',
        'last_name' => 'Obi',
        'gender' => 'Female',
        'date_of_birth' => Carbon::parse('2014-05-16'),
        'house' => 'Blue',
        'club' => 'Science',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'class_section_id' => null,
        'parent_id' => $this->parent->id,
        'admission_date' => Carbon::parse('2020-09-10'),
        'status' => 'active',
    ]);

    $this->staffUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'teacher',
        'status' => 'active',
    ]);

    $this->staff = Staff::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $this->staffUser->id,
        'full_name' => 'Mr Obi',
        'email' => 'obi@example.com',
        'phone' => '08012345678',
        'role' => 'Mathematics',
        'gender' => 'male',
        'employment_start_date' => Carbon::parse('2022-01-01'),
    ]);
});

it('records student attendance and updates duplicates', function () {
    postJson(route('attendance.students.store'), [
        'date' => '2025-10-20',
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'entries' => [
            [
                'student_id' => $this->student->id,
                'status' => 'present',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 0);

    postJson(route('attendance.students.store'), [
        'date' => '2025-10-20',
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'entries' => [
            [
                'student_id' => $this->student->id,
                'status' => 'late',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('updated', 1);

    expect(Attendance::where('student_id', $this->student->id)->count())->toBe(1)
        ->and(Attendance::where('student_id', $this->student->id)->value('status'))->toBe('late');
});

it('returns student attendance reports with status breakdowns', function () {
    Attendance::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'date' => Carbon::parse('2025-10-20'),
        'status' => 'absent',
        'recorded_by' => $this->user->id,
    ]);

    Attendance::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'date' => Carbon::parse('2025-10-21'),
        'status' => 'present',
        'recorded_by' => $this->user->id,
    ]);

    getJson(route('attendance.students.report', [
        'from' => '2025-10-01',
        'to' => '2025-10-31',
    ]))
        ->assertOk()
        ->assertJsonPath('summary.total_records', 2)
        ->assertJsonPath('status_breakdown.absent', 1)
        ->assertJsonPath('status_breakdown.present', 1);
});

it('records staff attendance for multiple staff entries', function () {
    postJson(route('attendance.staff.store'), [
        'date' => '2025-10-20',
        'entries' => [
            [
                'staff_id' => $this->staff->id,
                'status' => 'present',
                'branch_name' => 'Main Campus',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 0);

    expect(StaffAttendance::where('staff_id', $this->staff->id)->count())->toBe(1)
        ->and(StaffAttendance::where('staff_id', $this->staff->id)->value('branch_name'))->toBe('Main Campus');
});
