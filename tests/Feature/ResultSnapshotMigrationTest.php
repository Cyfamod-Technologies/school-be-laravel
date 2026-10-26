<?php

use App\Models\ClassArm;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('resumes the result placement backfill without replacing existing snapshots', function () {
    $school = School::factory()->create();
    $session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'start_date' => now()->subMonths(6),
        'end_date' => now()->addMonths(6),
        'status' => 'active',
    ]);
    $term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'session_id' => $session->id,
        'name' => 'First Term',
        'term_number' => 1,
        'slug' => 'first-term',
        'start_date' => now()->subMonths(5),
        'end_date' => now()->subMonths(2),
        'status' => 'active',
    ]);
    $currentClass = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'T.C 1',
        'slug' => 'tc-1',
    ]);
    $previousClass = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'T.C 0',
        'slug' => 'tc-0',
    ]);
    $arm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $currentClass->id,
        'name' => 'Business',
        'slug' => 'business',
    ]);
    $student = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'admission_no' => 'TC-001',
        'first_name' => 'Test',
        'last_name' => 'Student',
        'gender' => 'F',
        'date_of_birth' => now()->subYears(12),
        'current_session_id' => $session->id,
        'current_term_id' => $term->id,
        'school_class_id' => $currentClass->id,
        'class_arm_id' => $arm->id,
        'admission_date' => now()->subYear(),
        'status' => 'active',
    ]);
    $subjects = collect(['Marketing', 'Commerce'])->map(fn (string $name) => Subject::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => $name,
        'code' => strtoupper(substr($name, 0, 3)),
    ]));

    $missingSnapshot = Result::create([
        'id' => (string) Str::uuid(),
        'student_id' => $student->id,
        'subject_id' => $subjects[0]->id,
        'session_id' => $session->id,
        'term_id' => $term->id,
        'school_class_id' => null,
        'class_arm_id' => null,
        'total_score' => 72,
    ]);
    $existingSnapshot = Result::create([
        'id' => (string) Str::uuid(),
        'student_id' => $student->id,
        'subject_id' => $subjects[1]->id,
        'session_id' => $session->id,
        'term_id' => $term->id,
        'school_class_id' => $previousClass->id,
        'total_score' => 81,
    ]);

    $migration = require database_path('migrations/2026_08_01_000001_add_class_snapshot_to_results_table.php');
    $migration->up();
    $migration->up();

    expect($missingSnapshot->fresh()->school_class_id)->toBe($currentClass->id)
        ->and($missingSnapshot->fresh()->class_arm_id)->toBe($arm->id)
        ->and($missingSnapshot->fresh()->total_score)->toBe(72.0)
        ->and($existingSnapshot->fresh()->school_class_id)->toBe($previousClass->id)
        ->and($existingSnapshot->fresh()->total_score)->toBe(81.0);

    $migration->down();

    expect(Schema::hasColumn('results', 'school_class_id'))->toBeTrue()
        ->and($missingSnapshot->fresh()->school_class_id)->toBe($currentClass->id);
});
