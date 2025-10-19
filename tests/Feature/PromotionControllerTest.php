<?php

use App\Models\ClassArm;
use App\Models\PromotionLog;
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

describe('PromotionController', function () {
    beforeEach(function () {
        $this->school = School::factory()->create();

        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->admin, [], 'sanctum');

        $this->sourceSession = Session::create([
            'id' => (string) Str::uuid(),
            'school_id' => $this->school->id,
            'name' => '2024/2025',
            'slug' => '2024-2025',
            'start_date' => now()->subMonths(8),
            'end_date' => now()->addMonths(4),
            'status' => 'completed',
        ]);

        $this->targetSession = Session::create([
            'id' => (string) Str::uuid(),
            'school_id' => $this->school->id,
            'name' => '2025/2026',
            'slug' => '2025-2026',
            'start_date' => now()->addMonths(5),
            'end_date' => now()->addMonths(17),
            'status' => 'planned',
        ]);

        $this->term = Term::create([
            'id' => (string) Str::uuid(),
            'school_id' => $this->school->id,
            'session_id' => $this->sourceSession->id,
            'name' => 'First Term',
            'slug' => 'first-term',
            'start_date' => now()->subMonths(7),
            'end_date' => now()->subMonths(4),
            'status' => 'completed',
        ]);

        $this->class = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'school_id' => $this->school->id,
            'name' => 'JSS 1',
            'slug' => 'jss-1',
        ]);

        $this->nextClass = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'school_id' => $this->school->id,
            'name' => 'JSS 2',
            'slug' => 'jss-2',
        ]);

        $this->classArm = ClassArm::create([
            'id' => (string) Str::uuid(),
            'school_class_id' => $this->class->id,
            'name' => 'Blue',
            'slug' => 'blue',
        ]);

        $this->nextClassArm = ClassArm::create([
            'id' => (string) Str::uuid(),
            'school_class_id' => $this->nextClass->id,
            'name' => 'Gold',
            'slug' => 'gold',
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
            'first_name' => 'Parent',
            'last_name' => 'Example',
        ]);

        $this->students = collect(range(1, 3))->map(function ($i) {
            return Student::create([
                'id' => (string) Str::uuid(),
                'school_id' => $this->school->id,
                'admission_no' => '2024/0' . $i,
                'first_name' => 'Student ' . $i,
                'last_name' => 'Example',
                'gender' => 'M',
                'date_of_birth' => now()->subYears(10 + $i),
                'house' => 'House ' . $i,
                'club' => 'Club ' . $i,
                'current_session_id' => $this->sourceSession->id,
                'current_term_id' => $this->term->id,
                'school_class_id' => $this->class->id,
                'class_arm_id' => $this->classArm->id,
                'parent_id' => $this->parent->id,
                'admission_date' => now()->subYears(3),
                'status' => 'active',
            ]);
        });
    });

    it('promotes students in bulk and logs the action', function () {
        $payload = [
            'current_session_id' => $this->sourceSession->id,
            'target_session_id' => $this->targetSession->id,
            'target_class_id' => $this->nextClass->id,
            'target_class_arm_id' => $this->nextClassArm->id,
            'retain_subjects' => false,
            'student_ids' => $this->students->pluck('id')->all(),
        ];

        postJson(route('promotions.bulk'), $payload)
            ->assertOk()
            ->assertJsonPath('data.0.student_id', $this->students->first()->id);

        $this->students->each(function (Student $student) {
            $student->refresh();
            expect($student->current_session_id)->toBe($this->targetSession->id);
            expect($student->school_class_id)->toBe($this->nextClass->id);
            expect($student->class_arm_id)->toBe($this->nextClassArm->id);
        });

        expect(PromotionLog::query()->count())->toBe($this->students->count());
    });

    it('returns promotion history', function () {
        $log = PromotionLog::create([
            'student_id' => $this->students->first()->id,
            'from_session_id' => $this->sourceSession->id,
            'to_session_id' => $this->targetSession->id,
            'from_class_id' => $this->class->id,
            'to_class_id' => $this->nextClass->id,
            'performed_by' => $this->admin->id,
            'promoted_at' => now(),
        ]);

        getJson(route('promotions.history'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $log->id);
    });
});
