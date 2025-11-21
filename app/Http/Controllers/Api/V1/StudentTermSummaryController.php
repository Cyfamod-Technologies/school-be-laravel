<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TermSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentTermSummaryController extends Controller
{
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

        $defaultTeacher = $this->generateTeacherComment($termSummary);
        $defaultPrincipal = $this->generatePrincipalComment($termSummary);

        return response()->json([
            'data' => [
                'class_teacher_comment' => $termSummary?->overall_comment ?? $defaultTeacher,
                'principal_comment' => $termSummary?->principal_comment ?? $defaultPrincipal,
            ],
        ]);
    }

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

        $termSummary->overall_comment = $validated['class_teacher_comment'] ?? null;
        $termSummary->principal_comment = $validated['principal_comment'] ?? null;
        $termSummary->save();

        return response()->json([
            'message' => 'Comments updated successfully.',
            'data' => [
                'class_teacher_comment' => $termSummary->overall_comment,
                'principal_comment' => $termSummary->principal_comment,
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
}
