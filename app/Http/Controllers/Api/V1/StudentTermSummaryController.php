<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommentRange;
use App\Models\GradingScale;
use App\Models\Student;
use App\Models\TermSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentTermSummaryController extends Controller
{
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
            $teacherComment = $this->generateTeacherComment($termSummary);
        }

        if ($principalComment === null || trim((string) $principalComment) === '') {
            $principalComment = $this->generatePrincipalComment($termSummary);
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
    }

    private function generateTeacherComment(?TermSummary $summary): string
    {
        if (! $summary || $summary->average_score === null) {
            return 'This student is good.';
        }

        $average = (float) $summary->average_score;

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

    private function generatePrincipalComment(?TermSummary $summary): string
    {
        if (! $summary || $summary->average_score === null) {
            return 'This student is hardworking.';
        }

        $average = (float) $summary->average_score;

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
}
