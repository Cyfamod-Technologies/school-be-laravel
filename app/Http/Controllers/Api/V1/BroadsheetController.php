<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\Term;
use App\Models\TermSummary;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BroadsheetController extends Controller
{
    public function print(Request $request)
    {
        $this->ensurePermission($request, ['results.view', 'students.view']);

        $user = $request->user();
        $schoolId = optional($user->school)->id;

        if (! $schoolId) {
            abort(403, 'You are not linked to any school.');
        }

        $validated = $request->validate([
            'session_id' => [
                'required',
                'uuid',
                Rule::exists('sessions', 'id')->where('school_id', $schoolId),
            ],
            'term_id' => [
                'required',
                'uuid',
                Rule::exists('terms', 'id')->where('school_id', $schoolId),
            ],
            'school_class_id' => [
                'required',
                'uuid',
                Rule::exists('classes', 'id')->where('school_id', $schoolId),
            ],
            'class_arm_id' => ['nullable', 'uuid'],
        ]);

        $session = Session::query()->find($validated['session_id']);
        $term = Term::query()->find($validated['term_id']);
        $class = SchoolClass::query()->find($validated['school_class_id']);
        $classArm = null;
        if (! empty($validated['class_arm_id'])) {
            $classArm = ClassArm::query()->whereKey($validated['class_arm_id'])->first();
        }

        // Subjects assigned to this class (optionally filtered by arm)
        $subjectQuery = SubjectAssignment::query()
            ->with('subject:id,name,code')
            ->where('school_class_id', $validated['school_class_id']);

        if (! empty($validated['class_arm_id'])) {
            $subjectQuery->where(function ($q) use ($validated) {
                $q->whereNull('class_arm_id')
                    ->orWhere('class_arm_id', $validated['class_arm_id']);
            });
        }

        $subjects = $subjectQuery
            ->get()
            ->map(fn ($a) => $a->subject)
            ->filter()
            ->unique('id')
            ->values();

        // Students in the class/arm
        $students = Student::query()
            ->where('school_id', $schoolId)
            ->where('school_class_id', $validated['school_class_id'])
            ->when(
                ! empty($validated['class_arm_id']),
                fn ($q) => $q->where('class_arm_id', $validated['class_arm_id'])
            )
            ->whereNotIn('status', ['inactive', 'Inactive'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $studentIds = $students->pluck('id');

        // Aggregate results for the selected term. If a subject summary row is absent,
        // fall back to the sum of that subject's assessment components.
        $results = Result::query()
            ->whereIn('student_id', $studentIds)
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->with('grade_range:id,grade_label')
            ->get()
            ->groupBy('student_id');

        $summaries = TermSummary::query()
            ->whereIn('student_id', $studentIds)
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->get()
            ->keyBy('student_id');

        $rows = $students->map(function (Student $student, int $index) use ($results, $summaries, $subjects) {
            $studentResults = $results->get($student->id, collect());
            $bySubject = $studentResults->groupBy('subject_id');

            $subjectScores = $subjects->map(function ($subject) use ($bySubject) {
                $subjectEntries = $bySubject->get($subject->id, collect());
                $total = $this->resolveResultTotalForEntries($subjectEntries);

                if ($total === null) {
                    return ['score' => '', 'grade' => ''];
                }

                $result = $subjectEntries->first(function (Result $entry) {
                    return $entry->assessment_component_id === null && $entry->total_score !== null;
                }) ?? $subjectEntries->first();

                return [
                    'score' => number_format($total, 0),
                    'grade' => $result->grade_range?->grade_label ?? '',
                ];
            });

            $summary = $summaries->get($student->id);
            $passes = $subjects->filter(function ($subject) use ($bySubject) {
                $total = $this->resolveResultTotalForEntries(
                    $bySubject->get($subject->id, collect())
                );

                return $total !== null && $total >= 40;
            })->count();

            return [
                'sno'        => $index + 1,
                'student'    => $student,
                'scores'     => $subjectScores,
                'average'    => $summary?->average_score !== null ? number_format((float) $summary->average_score, 1) : '',
                'position'   => $summary?->position_in_class ?? '',
                'passes'     => $passes,
            ];
        });

        return response()->view('broadsheet', [
            'school' => $user->school,
            'session' => $session,
            'term' => $term,
            'class' => $class,
            'classArm' => $classArm,
            'subjects' => $subjects,
            'rows' => $rows,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'filters' => [
                'session' => $session?->name,
                'term' => $term?->name,
                'class' => $class?->name,
                'class_arm' => $classArm?->name,
                'student_count' => $rows->count(),
            ],
        ], 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    private function resolveResultTotalForEntries(Collection $entries): ?float
    {
        if ($entries->isEmpty()) {
            return null;
        }

        $overall = $entries->first(function (Result $result) {
            return $result->assessment_component_id === null && $result->total_score !== null;
        });

        if ($overall && $overall->total_score !== null) {
            return round((float) $overall->total_score, 2);
        }

        $componentTotal = $entries
            ->filter(fn (Result $result) => $result->assessment_component_id !== null)
            ->pluck('total_score')
            ->filter(fn ($value) => $value !== null)
            ->sum();

        $hasComponentScores = $entries->contains(
            fn (Result $result) => $result->assessment_component_id !== null
        );

        return $hasComponentScores ? round((float) $componentTotal, 2) : null;
    }
}
