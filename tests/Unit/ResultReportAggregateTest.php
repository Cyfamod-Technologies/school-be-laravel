<?php

use App\Http\Controllers\ResultViewController;
use App\Models\Student;

it('calculates report aggregates using offered subjects and student class averages', function () {
    $student = new Student;
    $student->id = 'student-1';

    $method = new ReflectionMethod(ResultViewController::class, 'computeOverallStatistics');

    $result = $method->invoke(
        new ResultViewController,
        collect(array_fill(0, 10, ['average' => 0])),
        collect([
            'student-1' => 852,
            'student-2' => 800,
            'student-3' => 748,
        ]),
        $student,
        3,
        collect(),
        10,
    );

    expect($result['total_possible'])->toBe(1000)
        ->and($result['total_obtained'])->toBe(852.0)
        ->and($result['average'])->toBe(85.2)
        ->and($result['class_average'])->toBe(80.0);
});
