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

        return response()->json([
            'data' => [
                'class_teacher_comment' => $termSummary?->overall_comment,
                'principal_comment' => $termSummary?->principal_comment,
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
}
