<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Support\SimplePdfBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class StudentAttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'excused'];

    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request);

        $perPage = min($request->integer('per_page', 50), 200);

        $paginator = $query
            ->orderByDesc('date')
            ->orderBy('student_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Attendance $attendance) => $this->transformAttendance($attendance));

        return response()->json($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'session_id' => ['nullable', 'uuid'],
            'term_id' => ['nullable', 'uuid'],
            'school_class_id' => ['nullable', 'uuid'],
            'class_arm_id' => ['nullable', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
            'student_id' => ['required_without:entries', 'uuid'],
            'status' => ['required_without:entries', Rule::in(self::STATUSES)],
            'metadata' => ['nullable', 'array'],
            'entries' => ['required_without:student_id', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'uuid'],
            'entries.*.status' => ['required', Rule::in(self::STATUSES)],
            'entries.*.metadata' => ['sometimes', 'array'],
        ]);

        $entries = $this->normalizeEntries($validated);

        $studentIds = $entries->pluck('student_id')->unique();

        if ($studentIds->isEmpty()) {
            return response()->json(['message' => 'No attendance entries were provided.'], 422);
        }

        $user = $request->user();
        $students = Student::query()
            ->where('school_id', $user->school_id)
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        if ($students->count() !== $studentIds->count()) {
            $missing = $studentIds->diff($students->keys())->values();
            return response()->json([
                'message' => 'One or more students could not be found in your school.',
                'missing_student_ids' => $missing,
            ], 422);
        }

        $date = Carbon::parse($validated['date'])->toDateString();
        $sessionId = $validated['session_id'] ?? null;
        $termId = $validated['term_id'] ?? null;
        $classId = $validated['school_class_id'] ?? null;
        $classArmId = $validated['class_arm_id'] ?? null;
        $classSectionId = $validated['class_section_id'] ?? null;

        $missingContext = collect();

        foreach ($entries as $entry) {
            $student = $students->get($entry['student_id']);

            $resolvedSession = $sessionId ?? $student->current_session_id;
            $resolvedTerm = $termId ?? $student->current_term_id;

            if (! $resolvedSession || ! $resolvedTerm) {
                $missingContext->push($student->id);
            }
        }

        if ($missingContext->isNotEmpty()) {
            return response()->json([
                'message' => 'Session and term must be provided either explicitly or via student context.',
                'student_ids' => $missingContext->unique()->values(),
            ], 422);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use (
            $entries,
            $students,
            $date,
            $user,
            $sessionId,
            $termId,
            $classId,
            $classArmId,
            $classSectionId,
            &$created,
            &$updated
        ) {
            foreach ($entries as $entry) {
                $student = $students->get($entry['student_id']);

                $payload = [
                    'session_id' => $sessionId ?? $student->current_session_id,
                    'term_id' => $termId ?? $student->current_term_id,
                    'school_class_id' => $classId ?? $student->school_class_id,
                    'class_arm_id' => $classArmId ?? $student->class_arm_id,
                    'class_section_id' => $classSectionId ?? $student->class_section_id,
                    'status' => $entry['status'],
                    'recorded_by' => $user->id,
                    'metadata' => $entry['metadata'] ?? null,
                ];

                $attendance = Attendance::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'date' => $date,
                    ],
                    $payload
                );

                if ($attendance->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        return response()->json([
            'message' => 'Attendance saved successfully.',
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    public function update(Attendance $attendance, Request $request): JsonResponse
    {
        $this->authorizeAttendance($attendance, $request);

        $validated = $request->validate([
            'date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'session_id' => ['sometimes', 'uuid'],
            'term_id' => ['sometimes', 'uuid'],
            'school_class_id' => ['sometimes', 'uuid', 'nullable'],
            'class_arm_id' => ['sometimes', 'uuid', 'nullable'],
            'class_section_id' => ['sometimes', 'uuid', 'nullable'],
            'metadata' => ['nullable', 'array'],
        ]);

        $attendance->fill($validated);

        if (array_key_exists('date', $validated)) {
            $attendance->date = Carbon::parse($validated['date'])->toDateString();
        }

        if ($attendance->isDirty('date')) {
            $exists = Attendance::query()
                ->where('student_id', $attendance->student_id)
                ->whereDate('date', $attendance->date)
                ->where('id', '!=', $attendance->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'An attendance record already exists for this student on the specified date.',
                ], 422);
            }
        }

        if ($attendance->isDirty()) {
            $attendance->recorded_by = $request->user()->id;
            $attendance->save();
        }

        $attendance->loadMissing([
            'student',
            'session',
            'term',
            'schoolClass',
            'classArm',
            'classSection',
            'recorder',
        ]);

        return response()->json([
            'message' => 'Attendance updated.',
            'data' => $this->transformAttendance($attendance),
        ]);
    }

    public function destroy(Attendance $attendance, Request $request): JsonResponse
    {
        $this->authorizeAttendance($attendance, $request);
        $attendance->delete();

        return response()->json([
            'message' => 'Attendance record deleted.',
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request);

        $statusBreakdown = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $dailyBreakdown = (clone $query)
            ->select([
                'date',
                DB::raw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late"),
                DB::raw("SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused"),
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->limit(120)
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->toDateString(),
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'late' => (int) $row->late,
                'excused' => (int) $row->excused,
            ]);

        $studentsAtRiskRaw = (clone $query)
            ->select([
                'student_id',
                DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days"),
            ])
            ->groupBy('student_id')
            ->havingRaw('SUM(CASE WHEN status = \'absent\' THEN 1 ELSE 0 END) > 0')
            ->orderByDesc('absent_days')
            ->limit(15)
            ->get();

        $studentsById = Student::query()
            ->select('id', 'first_name', 'last_name', 'admission_no')
            ->whereIn('id', $studentsAtRiskRaw->pluck('student_id'))
            ->get()
            ->keyBy('id');

        $studentsAtRisk = $studentsAtRiskRaw
            ->map(function ($row) use ($studentsById) {
                $student = $studentsById->get($row->student_id);

                if (! $student) {
                    return null;
                }

                return [
                    'student_id' => $row->student_id,
                    'student_name' => trim($student->first_name . ' ' . $student->last_name),
                    'admission_no' => $student->admission_no,
                    'absent_days' => (int) $row->absent_days,
                    'late_days' => (int) $row->late_days,
                ];
            })
            ->filter()
            ->values();

        $summaryQuery = clone $query;

        $summary = [
            'total_records' => (clone $summaryQuery)->count(),
            'unique_students' => (clone $summaryQuery)->distinct('student_id')->count('student_id'),
            'date_range' => [
                'from' => (clone $summaryQuery)->min('date'),
                'to' => (clone $summaryQuery)->max('date'),
            ],
        ];

        return response()->json([
            'summary' => $summary,
            'status_breakdown' => $statusBreakdown,
            'daily_breakdown' => $dailyBreakdown,
            'students_at_risk' => $studentsAtRisk,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $query = $this->baseQuery($request);

        $records = $query
            ->orderBy('date')
            ->orderBy('student_id')
            ->limit(2000)
            ->get()
            ->map(fn (Attendance $attendance) => $this->transformAttendance($attendance));

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="student-attendance-report.csv"',
        ];

        $csvLines = [];
        $csvLines[] = implode(',', [
            'Date',
            'Student Name',
            'Admission No',
            'Status',
            'Session',
            'Term',
            'Class',
            'Class Arm',
            'Section',
            'Recorded By',
        ]);

        foreach ($records as $record) {
            $csvLines[] = implode(',', [
                $record['date'],
                $this->escapeCsv($record['student']['name'] ?? ''),
                $this->escapeCsv($record['student']['admission_no'] ?? ''),
                $record['status'],
                $this->escapeCsv($record['session']['name'] ?? ''),
                $this->escapeCsv($record['term']['name'] ?? ''),
                $this->escapeCsv($record['class']['name'] ?? ''),
                $this->escapeCsv($record['class_arm']['name'] ?? ''),
                $this->escapeCsv($record['class_section']['name'] ?? ''),
                $this->escapeCsv($record['recorded_by']['name'] ?? ''),
            ]);
        }

        return Response::make(implode("\n", $csvLines), 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $query = $this->baseQuery($request);

        $records = $query
            ->orderBy('date')
            ->orderBy('student_id')
            ->limit(1000)
            ->get()
            ->map(fn (Attendance $attendance) => $this->transformAttendance($attendance));

        $builder = new SimplePdfBuilder();
        $builder->addLine('Student Attendance Report')
            ->addLine('Generated: ' . now()->toDateTimeString())
            ->addBlankLine();

        foreach ($records as $record) {
            $builder->addLine(sprintf(
                '%s | %s | %s | %s',
                $record['date'],
                $record['student']['name'] ?? 'Unknown Student',
                strtoupper($record['status']),
                $record['class']['name'] ?? 'N/A'
            ));
        }

        if ($records->isEmpty()) {
            $builder->addLine('No attendance records match the selected filters.');
        }

        $pdfContent = $builder->build();

        return Response::make($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="student-attendance-report.pdf"',
        ]);
    }

    private function baseQuery(Request $request)
    {
        $user = $request->user();

        $query = Attendance::query()
            ->with([
                'student:id,first_name,last_name,admission_no,school_id',
                'session:id,name',
                'term:id,name',
                'schoolClass:id,name',
                'classArm:id,name',
                'classSection:id,name',
                'recorder:id,name',
            ])
            ->whereHas('student', fn ($q) => $q->where('school_id', $user->school_id));

        return $this->applyFilters($query, $request);
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('date')) {
            $query->whereDate('date', Carbon::parse($request->input('date'))->toDateString());
        }

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', Carbon::parse($request->input('from'))->toDateString());
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', Carbon::parse($request->input('to'))->toDateString());
        }

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->input('session_id'));
        }

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->input('term_id'));
        }

        if ($request->filled('school_class_id')) {
            $query->where('school_class_id', $request->input('school_class_id'));
        }

        if ($request->filled('class_arm_id')) {
            $query->where('class_arm_id', $request->input('class_arm_id'));
        }

        if ($request->filled('class_section_id')) {
            $query->where('class_section_id', $request->input('class_section_id'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->whereHas('student', function ($studentQuery) use ($search) {
                $studentQuery->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('admission_no', 'like', '%' . $search . '%');
                });
            });
        }

        return $query;
    }

    private function transformAttendance(Attendance $attendance): array
    {
        $student = $attendance->student;

        return [
            'id' => $attendance->id,
            'date' => $attendance->date?->toDateString(),
            'status' => $attendance->status,
            'metadata' => $attendance->metadata ?? [],
            'student' => $student ? [
                'id' => $student->id,
                'admission_no' => $student->admission_no,
                'name' => trim($student->first_name . ' ' . $student->last_name),
            ] : null,
            'session' => $attendance->session ? [
                'id' => $attendance->session->id,
                'name' => $attendance->session->name,
            ] : null,
            'term' => $attendance->term ? [
                'id' => $attendance->term->id,
                'name' => $attendance->term->name,
            ] : null,
            'class' => $attendance->schoolClass ? [
                'id' => $attendance->schoolClass->id,
                'name' => $attendance->schoolClass->name,
            ] : null,
            'class_arm' => $attendance->classArm ? [
                'id' => $attendance->classArm->id,
                'name' => $attendance->classArm->name,
            ] : null,
            'class_section' => $attendance->classSection ? [
                'id' => $attendance->classSection->id,
                'name' => $attendance->classSection->name,
            ] : null,
            'recorded_by' => $attendance->recorder ? [
                'id' => $attendance->recorder->id,
                'name' => $attendance->recorder->name,
            ] : null,
            'created_at' => optional($attendance->created_at)->toISOString(),
            'updated_at' => optional($attendance->updated_at)->toISOString(),
        ];
    }

    private function normalizeEntries(array $validated): Collection
    {
        if (! empty($validated['entries'])) {
            return collect($validated['entries'])->map(fn ($entry) => [
                'student_id' => $entry['student_id'],
                'status' => $entry['status'],
                'metadata' => $entry['metadata'] ?? null,
            ]);
        }

        return collect([[
            'student_id' => $validated['student_id'],
            'status' => $validated['status'],
            'metadata' => $validated['metadata'] ?? null,
        ]]);
    }

    private function authorizeAttendance(Attendance $attendance, Request $request): void
    {
        $user = $request->user();
        $attendance->loadMissing('student');

        if (! $attendance->student || $attendance->student->school_id !== $user->school_id) {
            abort(403, 'You are not authorized to modify this attendance record.');
        }
    }

    private function escapeCsv(?string $value): string
    {
        $value ??= '';
        $needsQuotes = str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n");

        $escaped = str_replace('"', '""', $value);

        return $needsQuotes ? '"' . $escaped . '"' : $escaped;
    }
}
