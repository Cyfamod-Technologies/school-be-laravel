<?php

use App\Models\Staff;
use App\Models\Student;
use App\Models\SubjectTeacherAssignment;
use App\Models\ClassTeacher;
use App\Services\Teachers\TeacherAssignmentScope;
use Illuminate\Support\Str;

it('applies a subject teacher assignment to every term in its session', function () {
    $classId = (string) Str::uuid();
    $sessionId = (string) Str::uuid();
    $firstTermId = (string) Str::uuid();
    $secondTermId = (string) Str::uuid();
    $subjectId = (string) Str::uuid();

    $assignment = new SubjectTeacherAssignment([
        'subject_id' => $subjectId,
        'staff_id' => (string) Str::uuid(),
        'school_class_id' => $classId,
        'session_id' => $sessionId,
        'term_id' => $firstTermId,
    ]);

    $student = new Student([
        'school_class_id' => $classId,
    ]);

    $scope = new TeacherAssignmentScope(
        true,
        new Staff(),
        collect([$assignment]),
        collect(),
    );

    expect($scope->allowsStudentSubject(
        $student,
        $subjectId,
        $sessionId,
        $secondTermId,
    ))->toBeTrue();
});

it('does not carry a teacher assignment into another session', function () {
    $classId = (string) Str::uuid();
    $assignedSessionId = (string) Str::uuid();
    $subjectId = (string) Str::uuid();

    $assignment = new SubjectTeacherAssignment([
        'subject_id' => $subjectId,
        'staff_id' => (string) Str::uuid(),
        'school_class_id' => $classId,
        'session_id' => $assignedSessionId,
        'term_id' => (string) Str::uuid(),
    ]);

    $scope = new TeacherAssignmentScope(
        true,
        new Staff(),
        collect([$assignment]),
        collect(),
    );

    expect($scope->allowsStudentSubject(
        new Student(['school_class_id' => $classId]),
        $subjectId,
        (string) Str::uuid(),
        (string) Str::uuid(),
    ))->toBeFalse();
});

it('applies a class teacher assignment to every term in its session', function () {
    $classId = (string) Str::uuid();
    $sessionId = (string) Str::uuid();

    $assignment = new ClassTeacher([
        'staff_id' => (string) Str::uuid(),
        'school_class_id' => $classId,
        'session_id' => $sessionId,
        'term_id' => (string) Str::uuid(),
    ]);

    $scope = new TeacherAssignmentScope(
        true,
        new Staff(),
        collect(),
        collect([$assignment]),
    );

    expect($scope->allowsStudentSubject(
        new Student(['school_class_id' => $classId]),
        (string) Str::uuid(),
        $sessionId,
        (string) Str::uuid(),
    ))->toBeTrue();
});
