<?php

use App\Models\ClassArm;
use App\Models\ResultPin;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
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
        postJson(route('students.result-pins.store', ['student' => $this->student->id]), [
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'max_usage' => 5,
        ])->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.max_usage', 5);

        $pin = ResultPin::query()->where('student_id', $this->student->id)->first();

        expect($pin)->not->toBeNull()
            ->and($pin->max_usage)->toBe(5)
            ->and($pin->use_count)->toBe(0);
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
        ]);

        putJson(route('result-pins.invalidate', ['resultPin' => $pin->id]))
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $pin->refresh();

        expect($pin->status)->toBe('revoked');
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
});
