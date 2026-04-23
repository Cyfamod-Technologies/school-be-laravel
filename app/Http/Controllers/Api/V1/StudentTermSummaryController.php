<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommentRange;
use App\Models\GradingScale;
use App\Models\Student;
use App\Models\TermSummary;
use App\Services\Teachers\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentTermSummaryController extends Controller
{
    public function __construct(private TeacherAccessService $teacherAccess)
    {
    }

    public function batchIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'session_id' => [
                'required',
                'uuid',
                Rule::exists('sessions', 'id')->where('school_id', $user->school_id),
            ],
            'term_id' => [
                'required',
                'uuid',
                Rule::exists('terms', 'id')->where('school_id', $user->school_id),
            ],
            'school_class_id' => [
                'required',
                'uuid',
                Rule::exists('classes', 'id')->where('school_id', $user->school_id),
            ],
            'class_arm_id' => ['nullable', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
        ]);

        $studentQuery = Student::query()
            ->where('school_id', $user->school_id)
            ->where('school_class_id', $validated['school_class_id'])
            ->when(
                ! empty($validated['class_arm_id']),
                fn ($query) => $query->where('class_arm_id', $validated['class_arm_id'])
            )
            ->when(
                ! empty($validated['class_section_id']),
                fn ($query) => $query->where('class_section_id', $validated['class_section_id'])
            )
            ->whereNotIn('status', ['inactive', 'Inactive'])
            ->with([
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
            ]);

        $this->teacherAccess->forUser($user)->restrictStudentQuery($studentQuery);

        $students = $studentQuery
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $summaries = TermSummary::query()
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        return response()->json([
            'data' => $students
                ->map(fn (Student $student) => $this->serializeBatchSummary(
                    $student,
                    $summaries->get($student->id)
                ))
                ->values()
                ->all(),
        ]);
    }

    public function batchUpdate(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'session_id' => [
                'required',
                'uuid',
                Rule::exists('sessions', 'id')->where('school_id', $user->school_id),
            ],
            'term_id' => [
                'required',
                'uuid',
                Rule::exists('terms', 'id')->where('school_id', $user->school_id),
            ],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'uuid'],
            'entries.*.days_present' => ['nullable', 'integer', 'min:0'],
            'entries.*.days_absent' => ['nullable', 'integer', 'min:0'],
        ]);

        $entries = collect($validated['entries'])
            ->map(fn (array $entry) => [
                'student_id' => (string) $entry['student_id'],
                'days_present' => $entry['days_present'] ?? null,
                'days_absent' => $entry['days_absent'] ?? null,
            ])
            ->values();

        $incompleteEntries = $entries
            ->filter(fn (array $entry) => ($entry['days_present'] === null) xor ($entry['days_absent'] === null))
            ->pluck('student_id')
            ->values()
            ->all();

        if (! empty($incompleteEntries)) {
            return response()->json([
                'message' => 'Both days present and days absent must be provided together, or both left blank.',
                'student_ids' => $incompleteEntries,
            ], 422);
        }

        $students = Student::query()
            ->where('school_id', $user->school_id)
            ->whereIn('id', $entries->pluck('student_id'))
            ->with([
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
            ])
            ->get()
            ->keyBy('id');

        if ($students->count() !== $entries->count()) {
            return response()->json([
                'message' => 'One or more students could not be found in your school.',
                'missing_student_ids' => $entries
                    ->pluck('student_id')
                    ->reject(fn (string $studentId) => $students->has($studentId))
                    ->values()
                    ->all(),
            ], 422);
        }

        $scope = $this->teacherAccess->forUser($user);
        if ($scope->isTeacher()) {
            foreach ($entries as $entry) {
                $student = $students->get($entry['student_id']);

                if (! $student || ! $scope->allowsStudent($student)) {
                    abort(403, 'You are not allowed to manage records for one or more students.');
                }
            }
        }

        $savedSummaries = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use (
            $entries,
            $students,
            $validated,
            &$savedSummaries,
            &$created,
            &$updated
        ) {
            $existingSummaries = TermSummary::query()
                ->where('session_id', $validated['session_id'])
                ->where('term_id', $validated['term_id'])
                ->whereIn('student_id', $entries->pluck('student_id'))
                ->get()
                ->keyBy('student_id');

            foreach ($entries as $entry) {
                $student = $students->get($entry['student_id']);
                $summary = $existingSummaries->get($entry['student_id']);

                if (! $summary && $entry['days_present'] === null && $entry['days_absent'] === null) {
                    continue;
                }

                $isNewSummary = false;
                if (! $summary) {
                    $summary = $this->makeEmptySummary(
                        $student,
                        $validated['session_id'],
                        $validated['term_id']
                    );
                    $isNewSummary = true;
                }

                $summary->days_present = $entry['days_present'];
                $summary->days_absent = $entry['days_absent'];

                if ($isNewSummary || $summary->isDirty()) {
                    $summary->save();
                    if ($isNewSummary) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }

                $savedSummaries[$student->id] = $summary;
            }
        });

        return response()->json([
            'message' => 'Attendance summary saved successfully.',
            'created' => $created,
            'updated' => $updated,
            'data' => $entries
                ->map(function (array $entry) use ($students, $savedSummaries) {
                    $student = $students->get($entry['student_id']);
                    $summary = $savedSummaries[$entry['student_id']] ?? null;

                    return $this->serializeBatchSummary($student, $summary);
                })
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/students/{student}/term-summary",
     *     tags={"school-v1.4"},
     *     summary="Fetch a student's term summary comments",
     *     description="Returns class teacher and principal comments for the provided session/term or the student's current session/term.",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         description="Session ID (defaults to student's current session)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="term_id",
     *         in="query",
     *         required=false,
     *         description="Term ID (defaults to student's current term)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Term summary returned"),
     *     @OA\Response(response=422, description="Missing session or term"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);

        $sessionId = (string) $request->input('session_id', $student->current_session_id);
        $termId = (string) $request->input('term_id', $student->current_term_id);

        if (! $sessionId || ! $termId) {
            return response()->json([
                'message' => 'Session and term must be provided.',
            ], 422);
        }

        $termSummary = TermSummary::query()
            ->where('student_id', $student->id)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->first();

        $commentTemplates = $this->resolveCommentTemplates($student, $sessionId);

        $teacherComment = $termSummary?->overall_comment;
        $principalComment = $termSummary?->principal_comment;

        if ($teacherComment === null || trim((string) $teacherComment) === '') {
            $teacherComment = $this->generateTeacherComment($termSummary, $student, $sessionId);
        }

        if ($principalComment === null || trim((string) $principalComment) === '') {
            $principalComment = $this->generatePrincipalComment($termSummary, $student, $sessionId);
        }

        return response()->json([
            'data' => [
                'class_teacher_comment' => $teacherComment,
                'principal_comment' => $principalComment,
                'class_teacher_comment_options' => $commentTemplates['class_teacher_comment_options'],
                'principal_comment_options' => $commentTemplates['principal_comment_options'],
                'days_present' => $termSummary?->days_present,
                'days_absent' => $termSummary?->days_absent,
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/students/{student}/term-summary",
     *     tags={"school-v1.4"},
     *     summary="Update term summary comments for a student",
     *     description="Creates or updates class teacher and principal comments for a specific session and term.",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"session_id","term_id"},
     *             @OA\Property(property="session_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="term_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="class_teacher_comment", type="string", example="Showing steady improvement."),
     *             @OA\Property(property="principal_comment", type="string", example="Keep up the good work.")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Comments updated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);

        $validated = $request->validate([
            'session_id' => [
                'required',
                'uuid',
                Rule::exists('sessions', 'id')->where('school_id', $student->school_id),
            ],
            'term_id' => [
                'required',
                'uuid',
                Rule::exists('terms', 'id')->where('school_id', $student->school_id),
            ],
            'class_teacher_comment' => ['nullable', 'string', 'max:2000'],
            'principal_comment' => ['nullable', 'string', 'max:2000'],
            'days_present' => ['nullable', 'integer', 'min:0', 'required_with:days_absent'],
            'days_absent' => ['nullable', 'integer', 'min:0', 'required_with:days_present'],
        ]);

        $termSummary = TermSummary::query()
            ->where('student_id', $student->id)
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->first();

        if (! $termSummary) {
            $termSummary = new TermSummary();
            $termSummary->id = (string) Str::uuid();
            $termSummary->student_id = $student->id;
            $termSummary->session_id = $validated['session_id'];
            $termSummary->term_id = $validated['term_id'];
            $termSummary->total_marks_obtained = 0;
            $termSummary->total_marks_possible = 0;
            $termSummary->average_score = 0;
            $termSummary->position_in_class = 0;
            $termSummary->class_average_score = 0;
            $termSummary->days_present = null;
            $termSummary->days_absent = null;
            $termSummary->final_grade = null;
            $termSummary->overall_comment = null;
            $termSummary->principal_comment = null;
        }

        if (array_key_exists('class_teacher_comment', $validated)) {
            $termSummary->overall_comment = $validated['class_teacher_comment'] ?? null;
        }
        if (array_key_exists('principal_comment', $validated)) {
            $termSummary->principal_comment = $validated['principal_comment'] ?? null;
        }
        if (array_key_exists('days_present', $validated)) {
            $termSummary->days_present = $validated['days_present'];
        }
        if (array_key_exists('days_absent', $validated)) {
            $termSummary->days_absent = $validated['days_absent'];
        }
        $termSummary->save();

        $commentTemplates = $this->resolveCommentTemplates(
            $student,
            $validated['session_id'] ?? null
        );

        return response()->json([
            'message' => 'Comments updated successfully.',
            'data' => [
                'class_teacher_comment' => $termSummary->overall_comment,
                'principal_comment' => $termSummary->principal_comment,
                'class_teacher_comment_options' => $commentTemplates['class_teacher_comment_options'],
                'principal_comment_options' => $commentTemplates['principal_comment_options'],
                'days_present' => $termSummary->days_present,
                'days_absent' => $termSummary->days_absent,
            ],
        ]);
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        $user = $request->user();

        if (! $user || $user->school_id !== $student->school_id) {
            abort(403, 'You are not allowed to manage records for this student.');
        }

        $scope = $this->teacherAccess->forUser($user);
        if ($scope->isTeacher() && ! $scope->allowsStudent($student)) {
            abort(403, 'You are not allowed to manage records for this student.');
        }
    }

    private function generateTeacherComment(?TermSummary $summary, ?Student $student, ?string $sessionId): string
    {
        if (! $summary || $summary->average_score === null) {
            return 'This student is good.';
        }

        $average = (float) $summary->average_score;

        // Get comment ranges from database
        if ($student && $sessionId) {
            $commentRange = $this->findMatchingCommentRange($student, $sessionId, $average);
            if ($commentRange && ! empty(trim((string) $commentRange->teacher_comment))) {
                return trim((string) $commentRange->teacher_comment);
            }
        }

        // Fallback to default hardcoded comments
        if ($average >= 85) {
            return 'Excellent performance. Keep it up.';
        }

        if ($average >= 70) {
            return 'Very good performance. Keep working hard.';
        }

        if ($average >= 55) {
            return 'Good effort. There is room for improvement.';
        }

        if ($average >= 45) {
            return 'Fair performance. Encourage more focus and hard work.';
        }

        return 'Below expectation. Close monitoring and extra support are recommended.';
    }

    private function generatePrincipalComment(?TermSummary $summary, ?Student $student, ?string $sessionId): string
    {
        if (! $summary || $summary->average_score === null) {
            return 'This student is hardworking.';
        }

        $average = (float) $summary->average_score;

        // Get comment ranges from database
        if ($student && $sessionId) {
            $commentRange = $this->findMatchingCommentRange($student, $sessionId, $average);
            if ($commentRange && ! empty(trim((string) $commentRange->principal_comment))) {
                return trim((string) $commentRange->principal_comment);
            }
        }

        // Fallback to default hardcoded comments
        if ($average >= 85) {
            return 'An outstanding result. The school is proud of this performance.';
        }

        if ($average >= 70) {
            return 'A very good result. Maintain this level of commitment.';
        }

        if ($average >= 55) {
            return 'A good result. Greater consistency will yield even better outcomes.';
        }

        if ($average >= 45) {
            return 'A fair result. Increased effort and diligence are advised.';
        }

        return 'Performance is below the expected standard. Parents and teachers should work together to support this learner.';
    }

    private function findMatchingCommentRange(Student $student, string $sessionId, float $score): ?CommentRange
    {
        $defaultQuery = GradingScale::query()
            ->where('school_id', $student->school_id)
            ->with(['comment_ranges' => fn ($query) => $query->orderBy('min_score')]);

        $gradeScale = null;

        if ($sessionId) {
            $gradeScale = (clone $defaultQuery)
                ->where('session_id', $sessionId)
                ->first();
        }

        if (! $gradeScale) {
            $gradeScale = (clone $defaultQuery)
                ->whereNull('session_id')
                ->first();
        }

        if (! $gradeScale || $gradeScale->comment_ranges->isEmpty()) {
            return null;
        }

        /** @var Collection<int, CommentRange> $ranges */
        $ranges = $gradeScale->comment_ranges->sortBy('min_score')->values();

        // Find the matching comment range for the score
        foreach ($ranges as $range) {
            if ($score >= (float) $range->min_score && $score <= (float) $range->max_score) {
                return $range;
            }
        }

        return null;
    }

    private function resolveCommentTemplates(Student $student, ?string $sessionId): array
    {
        $defaultQuery = GradingScale::query()
            ->where('school_id', $student->school_id)
            ->with(['comment_ranges' => fn ($query) => $query->orderBy('created_at')]);

        $gradeScale = null;

        if ($sessionId) {
            $gradeScale = (clone $defaultQuery)
                ->where('session_id', $sessionId)
                ->first();
        }

        if (! $gradeScale) {
            $gradeScale = (clone $defaultQuery)
                ->whereNull('session_id')
                ->first();
        }

        if (! $gradeScale) {
            return [
                'class_teacher_comment_options' => [],
                'principal_comment_options' => [],
            ];
        }

        /** @var Collection<int, CommentRange> $ranges */
        $ranges = $gradeScale->comment_ranges->sortBy('created_at')->values();

        return [
            'class_teacher_comment_options' => $ranges
                ->pluck('teacher_comment')
                ->map(fn ($comment) => trim((string) $comment))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'principal_comment_options' => $ranges
                ->pluck('principal_comment')
                ->map(fn ($comment) => trim((string) $comment))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function makeEmptySummary(Student $student, string $sessionId, string $termId): TermSummary
    {
        $summary = new TermSummary();
        $summary->id = (string) Str::uuid();
        $summary->student_id = $student->id;
        $summary->session_id = $sessionId;
        $summary->term_id = $termId;
        $summary->total_marks_obtained = 0;
        $summary->total_marks_possible = 0;
        $summary->average_score = 0;
        $summary->position_in_class = 0;
        $summary->class_average_score = 0;
        $summary->days_present = null;
        $summary->days_absent = null;
        $summary->final_grade = null;
        $summary->overall_comment = null;
        $summary->principal_comment = null;

        return $summary;
    }

    private function serializeBatchSummary(?Student $student, ?TermSummary $summary): ?array
    {
        if (! $student) {
            return null;
        }

        return [
            'student' => [
                'id' => $student->id,
                'name' => trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->implode(' ')),
                'admission_no' => $student->admission_no,
                'class_label' => collect([
                    optional($student->school_class)->name,
                    optional($student->class_arm)->name,
                    optional($student->class_section)->name,
                ])->filter()->implode(' / '),
            ],
            'class_teacher_comment' => $summary?->overall_comment,
            'principal_comment' => $summary?->principal_comment,
            'days_present' => $summary?->days_present,
            'days_absent' => $summary?->days_absent,
        ];
    }
}
