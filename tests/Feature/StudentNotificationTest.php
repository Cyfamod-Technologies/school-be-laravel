<?php

use App\Jobs\SendAttendanceNotification;
use App\Jobs\SendResultPinNotification;
use App\Models\Attendance;
use App\Models\ClassArm;
use App\Models\ResultPin;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\StudentNotification;
use App\Models\Term;
use App\Models\User;
use App\Services\Notifications\StudentPushNotificationService;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $school = School::factory()->create();
    $session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026-notifications',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(8),
        'status' => 'active',
    ]);
    $term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'session_id' => $session->id,
        'name' => 'First Term',
        'slug' => 'first-term-notifications',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(2),
        'status' => 'active',
    ]);
    $class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'JSS 1',
        'slug' => 'jss-1-notifications',
    ]);
    $arm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $class->id,
        'name' => 'A',
        'slug' => 'a-notifications',
    ]);
    $parentUser = User::factory()->create([
        'school_id' => $school->id,
        'role' => 'parent',
        'status' => 'active',
    ]);
    $parent = SchoolParent::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'user_id' => $parentUser->id,
        'first_name' => 'Test',
        'last_name' => 'Parent',
    ]);

    $this->students = collect([1, 2])->map(fn (int $index) => Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'admission_no' => "NOTIFY-{$index}",
        'first_name' => "Student{$index}",
        'last_name' => 'Test',
        'gender' => 'M',
        'date_of_birth' => now()->subYears(10),
        'current_session_id' => $session->id,
        'current_term_id' => $term->id,
        'school_class_id' => $class->id,
        'class_arm_id' => $arm->id,
        'parent_id' => $parent->id,
        'admission_date' => now()->subYears(2),
        'status' => 'active',
    ]));

    $this->session = $session;
    $this->term = $term;
    $this->class = $class;
    $this->arm = $arm;
});

it('ignores an outdated attendance notification after a correction', function () {
    $attendance = Attendance::create([
        'student_id' => $this->students[0]->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->arm->id,
        'date' => now()->toDateString(),
        'status' => 'present',
        'notification_revision' => 2,
    ]);
    $service = Mockery::mock(StudentPushNotificationService::class);
    $service->shouldNotReceive('deliver');

    (new SendAttendanceNotification($attendance->id, 1))->handle($service);
});

it('keeps the actual result pin out of the push notification payload', function () {
    $student = $this->students[0];
    $pin = ResultPin::create([
        'student_id' => $student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->term->id,
        'pin_code' => 'SECRET12',
        'status' => 'active',
        'sent_at' => now(),
    ]);
    $service = Mockery::mock(StudentPushNotificationService::class);
    $service->shouldReceive('deliver')
        ->once()
        ->withArgs(function ($target, $type, $title, $body, $data): bool {
            return $target->id === $this->students[0]->id
                && $type === 'result_pin'
                && $title === 'Result PIN Available'
                && ! str_contains($body, 'SECRET12')
                && ! str_contains(json_encode($data), 'SECRET12');
        });

    (new SendResultPinNotification($pin->id))->handle($service);
});

it('moves a test phone token to the latest logged-in student', function () {
    $first = $this->students[0];
    $second = $this->students[1];
    $payload = [
        'token' => 'shared-fcm-token',
        'device_id' => 'test-installation-id',
        'platform' => 'android',
        'app_version' => '1.0.0',
    ];

    Sanctum::actingAs($first, [], 'student');
    postJson(route('student.devices.store'), $payload)->assertCreated();

    Sanctum::actingAs($second, [], 'student');
    postJson(route('student.devices.store'), $payload)->assertOk();

    expect(StudentDevice::query()->count())->toBe(1)
        ->and(StudentDevice::query()->value('student_id'))->toBe($second->id);
});

it('unregisters only the authenticated students device', function () {
    $student = $this->students[0];
    Sanctum::actingAs($student, [], 'student');

    postJson(route('student.devices.store'), [
        'token' => 'logout-token',
        'device_id' => 'logout-device',
        'platform' => 'ios',
    ])->assertCreated();

    deleteJson(route('student.devices.destroy'), ['token' => 'logout-token'])
        ->assertOk()
        ->assertJsonPath('deleted', 1);

    expect(StudentDevice::query()->count())->toBe(0);
});

it('lists and marks only the authenticated students notifications as read', function () {
    $student = $this->students[0];
    $otherStudent = $this->students[1];
    $notification = StudentNotification::create([
        'student_id' => $student->id,
        'type' => 'attendance',
        'title' => 'Attendance Updated',
        'body' => 'You were marked Present.',
        'data' => ['type' => 'attendance'],
        'dedupe_key' => 'attendance:test:1',
    ]);
    $otherNotification = StudentNotification::create([
        'student_id' => $otherStudent->id,
        'type' => 'result_pin',
        'title' => 'Result PIN Available',
        'body' => 'Your result PIN is available.',
        'data' => ['type' => 'result_pin'],
        'dedupe_key' => 'result-pin:test:2',
    ]);

    Sanctum::actingAs($student, [], 'student');

    getJson(route('student.notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(1, 'notifications.data');

    putJson(route('student.notifications.read', ['notification' => $notification->id]))
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id);

    putJson(route('student.notifications.read', ['notification' => $otherNotification->id]))
        ->assertNotFound();

    expect($notification->refresh()->read_at)->not->toBeNull()
        ->and($otherNotification->refresh()->read_at)->toBeNull();
});
