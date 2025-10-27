<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\SubjectAssignment;
use App\Models\SubjectTeacherAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademicAnalyticsController extends Controller
{
    private const PASS_MARK = 50;

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'session_id' => ['nullable', 'uuid'],
            'term_id' => ['nullable', 'uuid'],
            'class_id' => ['nullable', 'uuid'],
            'subject_id' => ['nullable', 'uuid'],
        ]);

        $filters = array_filter($validated);
        $schoolId = $user->school_id;

        $baseQuery = $this->baseResultsQuery($schoolId, $filters);

        if (! $baseQuery->exists()) {
            return response()->json([
                'summary' => [
                    'students' => 0,
                    'teachers' => 0,
                    'subjects' => 0,
                ],
                'class_metrics' => [],
                'subject_performance' => [
                    'top' => [],
                    'bottom' => [],
                ],
                'pass_fail' => [
                    'total_students' => 0,
                    'passing_students' => 0,
                    'failing_students' => 0,
                    'pass_rate' => 0,
                ],
            ]);
        }

        $classMetrics = $this->buildClassMetrics($schoolId, $filters);
        $subjectPerformance = $this->buildSubjectPerformance($schoolId, $filters);
        $passFail = $this->buildPassFailSummary($schoolId, $filters);

        $summary = [
            'students' => array_sum(array_column($classMetrics, 'student_count')),
            'teachers' => array_sum(array_column($classMetrics, 'teacher_count')),
            'subjects' => array_sum(array_column($classMetrics, 'subject_count')),
        ];

        return response()->json([
            'summary' => $summary,
            'class_metrics' => $classMetrics,
            'subject_performance' => $subjectPerformance,
            'pass_fail' => $passFail,
        ]);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function buildClassMetrics(string $schoolId, array $filters): array
    {
        $classAverages = $this->baseResultsQuery($schoolId, $filters)
            ->selectRaw('students.school_class_id as class_id')
            ->selectRaw('AVG(results.total_score) as average_score')
            ->selectRaw('COUNT(DISTINCT results.student_id) as student_count')
            ->whereNotNull('students.school_class_id')
            ->groupBy('students.school_class_id')
            ->get();

        $classIds = $classAverages->pluck('class_id')->filter()->unique()->all();

        if (empty($classIds)) {
            return [];
        }

        $classes = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->get()
            ->keyBy('id');

        $teacherCounts = $this->buildTeacherCounts($schoolId, $filters, $classIds);
        $subjectCounts = $this->buildSubjectCounts($schoolId, $filters, $classIds);

        return $classAverages
            ->map(function ($row) use ($classes, $teacherCounts, $subjectCounts) {
                $class = $classes->get($row->class_id);
                if (! $class) {
                    return null;
                }

                return [
                    'class_id' => $row->class_id,
                    'class_name' => $class->name,
                    'average_score' => round((float) $row->average_score, 2),
                    'student_count' => (int) $row->student_count,
                    'teacher_count' => (int) ($teacherCounts[$row->class_id] ?? 0),
                    'subject_count' => (int) ($subjectCounts[$row->class_id] ?? 0),
                ];
            })
            ->filter()
            ->sortByDesc('average_score')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $filters
     * @param  array<int, string>  $classIds
     */
    private function buildTeacherCounts(string $schoolId, array $filters, array $classIds): array
    {
        return SubjectTeacherAssignment::query()
            ->join('staff', 'staff.id', '=', 'subject_teacher_assignments.staff_id')
            ->where('staff.school_id', $schoolId)
            ->whereIn('subject_teacher_assignments.school_class_id', $classIds)
            ->when($filters['session_id'] ?? null, fn (Builder $query, string $value) => $query->where('subject_teacher_assignments.session_id', $value))
            ->when($filters['term_id'] ?? null, fn (Builder $query, string $value) => $query->where('subject_teacher_assignments.term_id', $value))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, string $value) => $query->where('subject_teacher_assignments.subject_id', $value))
            ->selectRaw('subject_teacher_assignments.school_class_id as class_id')
            ->selectRaw('COUNT(DISTINCT subject_teacher_assignments.staff_id) as teacher_count')
            ->groupBy('subject_teacher_assignments.school_class_id')
            ->pluck('teacher_count', 'class_id')
            ->toArray();
    }

    /**
     * @param  array<string, string>  $filters
     * @param  array<int, string>  $classIds
     */
    private function buildSubjectCounts(string $schoolId, array $filters, array $classIds): array
    {
        return SubjectAssignment::query()
            ->join('subjects', 'subjects.id', '=', 'subject_school_class_assignments.subject_id')
            ->where('subjects.school_id', $schoolId)
            ->whereIn('subject_school_class_assignments.school_class_id', $classIds)
            ->when($filters['subject_id'] ?? null, fn (Builder $query, string $value) => $query->where('subject_school_class_assignments.subject_id', $value))
            ->selectRaw('subject_school_class_assignments.school_class_id as class_id')
            ->selectRaw('COUNT(DISTINCT subject_school_class_assignments.subject_id) as subject_count')
            ->groupBy('subject_school_class_assignments.school_class_id')
            ->pluck('subject_count', 'class_id')
            ->toArray();
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function buildSubjectPerformance(string $schoolId, array $filters): array
    {
        $subjectAverages = $this->baseResultsQuery($schoolId, $filters)
            ->join('subjects', 'subjects.id', '=', 'results.subject_id')
            ->selectRaw('results.subject_id')
            ->selectRaw('subjects.name as subject_name')
            ->selectRaw('AVG(results.total_score) as average_score')
            ->selectRaw('COUNT(DISTINCT students.school_class_id) as class_count')
            ->groupBy('results.subject_id', 'subjects.name')
            ->get()
            ->map(function ($row) {
                return [
                    'subject_id' => $row->subject_id,
                    'subject_name' => $row->subject_name,
                    'average_score' => round((float) $row->average_score, 2),
                    'class_count' => (int) $row->class_count,
                ];
            })
            ->values();

        $top = $subjectAverages->sortByDesc('average_score')->take(5)->values()->all();
        $bottom = $subjectAverages->sortBy('average_score')->take(5)->values()->all();

        return [
            'top' => $top,
            'bottom' => $bottom,
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function buildPassFailSummary(string $schoolId, array $filters): array
    {
        $studentAverageQuery = $this->baseResultsQuery($schoolId, $filters)
            ->selectRaw('results.student_id as student_id')
            ->selectRaw('AVG(results.total_score) as average_score')
            ->groupBy('results.student_id');

        $aggregated = DB::query()
            ->fromSub($studentAverageQuery, 'student_scores')
            ->selectRaw('SUM(CASE WHEN average_score >= ? THEN 1 ELSE 0 END) as passing', [self::PASS_MARK])
            ->selectRaw('SUM(CASE WHEN average_score < ? THEN 1 ELSE 0 END) as failing', [self::PASS_MARK])
            ->selectRaw('COUNT(*) as total')
            ->first();

        $total = (int) ($aggregated->total ?? 0);
        $passing = (int) ($aggregated->passing ?? 0);
        $failing = (int) ($aggregated->failing ?? 0);
        $passRate = $total > 0 ? round(($passing / $total) * 100, 2) : 0;

        return [
            'total_students' => $total,
            'passing_students' => $passing,
            'failing_students' => $failing,
            'pass_rate' => $passRate,
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function baseResultsQuery(string $schoolId, array $filters): Builder
    {
        return Result::query()
            ->join('students', function ($join) use ($schoolId) {
                $join->on('students.id', '=', 'results.student_id')
                    ->where('students.school_id', '=', $schoolId);
            })
            ->when($filters['session_id'] ?? null, fn (Builder $query, string $value) => $query->where('results.session_id', $value))
            ->when($filters['term_id'] ?? null, fn (Builder $query, string $value) => $query->where('results.term_id', $value))
            ->when($filters['class_id'] ?? null, fn (Builder $query, string $value) => $query->where('students.school_class_id', $value))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, string $value) => $query->where('results.subject_id', $value));
    }
}
