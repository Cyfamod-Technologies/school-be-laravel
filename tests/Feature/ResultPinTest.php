<?php

use App\Jobs\SendResultPinNotification;
use App\Models\ClassArm;
use App\Models\ResultPin;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

describe('Result PIN management', function () {
    beforeEach(function () {
        $this->school = School::factory()->create();

        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->admin, [], 'sanctum');

        $this->session = Session::create([
            'id' => (string) Str::uuid(),
            'school_id' => $this->school->id,
            'name' => '2024/2025',
            'slug' => '2024-2025',
            'start_date' => now()->subMonths(5),
            'end_date' => now()->addMonths(5),
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
            'name' => 'JSS 2',
            'slug' => 'jss-2',
        ]);

        $this->classArm = ClassArm::create([
            'id' => (string) Str::uuid(),
            'school_class_id' => $this->class->id,
            'name' => 'Ruby',
            'slug' => 'ruby',
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
            'first_name' => 'Pat',
            'last_name' => 'Doe',
        ]);

        $this->students = collect(range(1, 3))->map(function ($index) {
            return Student::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'admission_no' => '2024/0' . $index,
                'first_name' => 'Student ' . $index,
                'last_name' => 'Example',
                'gender' => 'M',
                'date_of_birth' => now()->subYears(10 + $index),
                'house' => 'Blue',
                'club' => 'Music',
                'current_session_id' => $this->session->id,
                'current_term_id' => $this->term->id,
                'school_class_id' => $this->class->id,
                'class_arm_id' => $this->classArm->id,
                'parent_id' => $this->parent->id,
                'admission_date' => now()->subYears(3),
                'status' => 'active',
            ]);
        });

        $this->student = $this->students->first();
    });

    it('generates a result pin for a student', function () {
        Queue::fake();

        postJson(route('students.result-pins.store', ['student' => $this->student->id]), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'max_usage' => 5,
        ])->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.distribution_status', 'not_sent')
            ->assertJsonPath('notification_queued', false)
            ->assertJsonPath('data.max_usage', 5);

        $pin = ResultPin::query()->where('student_id', $this->student->id)->first();

        expect($pin)->not->toBeNull()
            ->and($pin->max_usage)->toBe(5)
            ->and($pin->use_count)->toBe(0)
            ->and($pin->sent_at)->toBeNull();

        Queue::assertNotPushed(SendResultPinNotification::class);
    });

    it('hides generated pins from the student until an administrator sends them', function () {
        Queue::fake();

        postJson(route('students.result-pins.store', ['student' => $this->student->id]), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'max_usage' => 3,
        ])->assertCreated();

        Sanctum::actingAs($this->student, [], 'student');

        getJson(route('student.result-pins.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('sends generated pins to selected student dashboards', function () {
        Queue::fake();

        foreach ($this->students->take(2) as $student) {
            postJson(route('students.result-pins.store', ['student' => $student->id]), [
                'session_id' => $this->session->id,
                'term_id' => $this->term->id,
                'max_usage' => 3,
            ])->assertCreated();
        }

        $selectedStudentIds = $this->students->take(2)->pluck('id')->all();

        postJson(route('result-pins.distribute'), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'student_ids' => $selectedStudentIds,
        ])->assertOk()
            ->assertJsonPath('sent_count', 2)
            ->assertJsonPath('already_sent_count', 0);

        expect(ResultPin::query()->whereIn('student_id', $selectedStudentIds)->whereNotNull('sent_at')->count())
            ->toBe(2);

        Queue::assertPushed(SendResultPinNotification::class, 2);

        Sanctum::actingAs($this->student, [], 'student');

        getJson(route('student.result-pins.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.distribution_status', 'sent');
    });

    it('does not partially send class pins when generated pins are insufficient', function () {
        postJson(route('students.result-pins.store', ['student' => $this->student->id]), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
        ])->assertCreated();

        postJson(route('result-pins.distribute'), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'school_class_id' => $this->class->id,
            'class_arm_id' => $this->classArm->id,
        ])->assertStatus(422)
            ->assertJsonPath('required', 3)
            ->assertJsonPath('available', 1);

        expect(ResultPin::query()->whereNotNull('sent_at')->count())->toBe(0);
    });

    it('prevents duplicate active pins unless regenerate is true', function () {
        postJson(route('students.result-pins.store', ['student' => $this->student->id]), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
        ])->assertCreated();

        postJson(route('students.result-pins.store', ['student' => $this->student->id]), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
        ])->assertStatus(422);
    });

    it('regenerates a result pin when requested', function () {
        postJson(route('students.result-pins.store', ['student' => $this->student->id]), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'max_usage' => 5,
        ])->assertCreated();

        $existing = ResultPin::first();
        $oldCode = $existing->pin_code;
        expect($existing->max_usage)->toBe(5);

        postJson(route('students.result-pins.store', ['student' => $this->student->id]), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'regenerate' => true,
            'max_usage' => 3,
        ])->assertCreated();

        $existing->refresh();

        expect(ResultPin::query()->where('student_id', $this->student->id)->count())->toBe(1)
            ->and($existing->status)->toBe('active')
            ->and($existing->pin_code)->not->toBe($oldCode)
            ->and($existing->max_usage)->toBe(3);
    });

    it('invalidates an existing pin', function () {
        $pin = ResultPin::create([
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'pin_code' => 'ABCDEFGH',
            'status' => 'active',
            'sent_at' => now(),
            'sent_by' => $this->admin->id,
        ]);

        putJson(route('result-pins.invalidate', ['resultPin' => $pin->id]))
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.distribution_status', 'not_sent');

        $pin->refresh();

        expect($pin->status)->toBe('revoked')
            ->and($pin->sent_at)->toBeNull()
            ->and($pin->sent_by)->toBeNull();
    });

    it('lists result pins for a student', function () {
        ResultPin::create([
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'pin_code' => 'PINCODE01',
            'status' => 'active',
        ]);

        ResultPin::create([
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'pin_code' => 'PINCODE02',
            'status' => 'revoked',
        ]);

        getJson(route('students.result-pins.index', ['student' => $this->student->id, 'session_id' => $this->session->id, 'term_id' => $this->term->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('bulk generates pins for a class', function () {
        postJson(route('result-pins.bulk-generate'), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'school_class_id' => $this->class->id,
            'max_usage' => 4,
        ])->assertOk();

        expect(ResultPin::query()->where('session_id', $this->session->id)->where('term_id', $this->term->id)->count())
            ->toBe($this->students->count())
            ->and(ResultPin::query()->where('session_id', $this->session->id)->where('term_id', $this->term->id)->where('max_usage', 4)->count())
            ->toBe($this->students->count());
    });

    it('lists pins with filters via global endpoint', function () {
        ResultPin::create([
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'pin_code' => 'PIN000AAA',
            'status' => 'active',
        ]);

        getJson(route('result-pins.index', ['session_id' => $this->session->id, 'term_id' => $this->term->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.id', $this->student->id);
    });

    it('hides student identity on printed scratch cards when the school setting is enabled', function () {
        $this->school->update([
            'result_hide_student_identity' => true,
        ]);

        ResultPin::create([
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'pin_code' => 'PINPRINT1',
            'status' => 'active',
        ]);

        getJson(route('result-pins.cards.print', [
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
        ]))
            ->assertOk()
            ->assertDontSee('Student 1 Example', false)
            ->assertDontSee($this->student->admission_no, false)
            ->assertDontSee('JSS 2 - Ruby', false)
            ->assertSee('PINPRINT1', false);
    });
});
