<?php

use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\Term;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

it('returns only class-wide and matching-arm subjects in the student profile', function () {
    $school = School::factory()->create();

    $session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'start_date' => now()->startOfYear(),
        'end_date' => now()->endOfYear(),
        'status' => 'active',
    ]);

    $term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'session_id' => $session->id,
        'name' => 'First Term',
        'slug' => 'first-term',
        'start_date' => now()->startOfYear(),
        'end_date' => now()->addMonths(3),
        'status' => 'active',
    ]);

    $class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'JSS 1',
        'slug' => 'jss-1',
    ]);

    $otherClass = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'JSS 2',
        'slug' => 'jss-2',
    ]);

    $studentArm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $class->id,
        'name' => 'A',
        'slug' => 'a',
    ]);

    $otherArm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $class->id,
        'name' => 'B',
        'slug' => 'b',
    ]);

    $student = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'admission_no' => 'ADM-001',
        'first_name' => 'Ada',
        'last_name' => 'Okafor',
        'gender' => 'F',
        'date_of_birth' => now()->subYears(12),
        'current_session_id' => $session->id,
        'current_term_id' => $term->id,
        'school_class_id' => $class->id,
        'class_arm_id' => $studentArm->id,
        'admission_date' => now()->subYears(3),
        'status' => 'active',
    ]);

    $subjects = collect([
        'Mathematics',
        'English Language',
        'French',
        'Biology',
    ])->mapWithKeys(function (string $name) use ($school) {
        $subject = Subject::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => $name,
        ]);

        return [$name => $subject];
    });

    foreach ([
        ['Mathematics', $class->id, null],
        ['English Language', $class->id, $studentArm->id],
        ['French', $class->id, $otherArm->id],
        ['Biology', $otherClass->id, null],
    ] as [$subjectName, $classId, $armId]) {
        SubjectAssignment::create([
            'id' => (string) Str::uuid(),
            'subject_id' => $subjects[$subjectName]->id,
            'school_class_id' => $classId,
            'class_arm_id' => $armId,
            'class_section_id' => null,
        ]);
    }

    Sanctum::actingAs($student, [], 'student');

    getJson('/api/v1/student/profile')
        ->assertOk()
        ->assertJsonPath('student.subjects', [
            ['id' => $subjects['English Language']->id, 'name' => 'English Language'],
            ['id' => $subjects['Mathematics']->id, 'name' => 'Mathematics'],
        ]);
});
