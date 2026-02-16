<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\CbtAssessmentLink;
use App\Models\CbtScoreImport;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CbtAssessmentLinkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request, string $componentId)
    {
        $this->ensurePermission($request, ['results.enter', 'cbt.view']);

        $user = $request->user();

        $component = AssessmentComponent::query()
            ->where('school_id', $user->school_id)
            ->findOrFail($componentId);

        $links = CbtAssessmentLink::query()
            ->where('assessment_component_id', $component->id)
            ->with([
                'cbtExam:id,title,subject_id,class_id',
                'class:id,name',
                'term:id,name',
                'session:id,name',
                'subject:id,name',
            ])
            ->withCount('pendingImports')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'component' => $component,
            'links' => $links,
        ]);
    }

    public function store(Request $request, string $componentId)
    {
        $this->ensurePermission($request, ['results.enter', 'cbt.view']);

        $validated = $request->validate([
            'cbt_exam_id' => ['required', 'uuid', 'exists:quizzes,id'],
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'term_id' => ['nullable', 'uuid', 'exists:terms,id'],
            'session_id' => ['nullable', 'uuid', 'exists:sessions,id'],
            'subject_id' => ['nullable', 'uuid', 'exists:subjects,id'],
            'auto_sync' => ['boolean'],
            'score_mapping_type' => ['nullable', 'in:direct,percentage,scaled'],
            'max_score_override' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $user = $request->user();

        $component = AssessmentComponent::query()
            ->where('school_id', $user->school_id)
            ->findOrFail($componentId);

        $quiz = Quiz::query()
            ->where('school_id', $user->school_id)
            ->findOrFail($validated['cbt_exam_id']);

        if (! empty($validated['class_id'])) {
            $classExists = SchoolClass::query()
                ->where('school_id', $user->school_id)
                ->where('id', $validated['class_id'])
                ->exists();
            if (! $classExists) {
                throw ValidationException::withMessages([
                    'class_id' => ['Selected class is not available for this school.'],
                ]);
            }
        }

        if (! empty($validated['subject_id'])) {
            $subjectExists = Subject::query()
                ->where('school_id', $user->school_id)
                ->where('id', $validated['subject_id'])
                ->exists();
            if (! $subjectExists) {
                throw ValidationException::withMessages([
                    'subject_id' => ['Selected subject is not available for this school.'],
                ]);
            }
        }

        if (! empty($validated['term_id'])) {
            $term = Term::query()
                ->where('school_id', $user->school_id)
                ->where('id', $validated['term_id'])
                ->first();
            if (! $term) {
                throw ValidationException::withMessages([
                    'term_id' => ['Selected term is not available for this school.'],
                ]);
            }

            if (! empty($validated['session_id']) && $term->session_id !== $validated['session_id']) {
                throw ValidationException::withMessages([
                    'session_id' => ['Selected term does not belong to the specified session.'],
                ]);
            }
        }

        if (! empty($validated['session_id'])) {
            $sessionExists = Session::query()
                ->where('school_id', $user->school_id)
                ->where('id', $validated['session_id'])
                ->exists();
            if (! $sessionExists) {
                throw ValidationException::withMessages([
                    'session_id' => ['Selected session is not available for this school.'],
                ]);
            }
        }

        if ($quiz->subject_id && ! empty($validated['subject_id']) && $quiz->subject_id !== $validated['subject_id']) {
            throw ValidationException::withMessages([
                'subject_id' => ['Selected subject must match the CBT exam subject.'],
            ]);
        }

        $resolvedSubjectId = $validated['subject_id'] ?? $quiz->subject_id;
        if ($resolvedSubjectId) {
            $componentSubjectIds = $component->subjects()->pluck('subjects.id');
            if ($componentSubjectIds->isNotEmpty() && ! $componentSubjectIds->contains($resolvedSubjectId)) {
                throw ValidationException::withMessages([
                    'subject_id' => ['Assessment component is not attached to the selected subject.'],
                ]);
            }
        }

        $link = CbtAssessmentLink::create([
            'assessment_component_id' => $component->id,
            'cbt_exam_id' => $quiz->id,
            'class_id' => $validated['class_id'] ?? null,
            'term_id' => $validated['term_id'] ?? null,
            'session_id' => $validated['session_id'] ?? null,
            'subject_id' => $validated['subject_id'] ?? null,
            'auto_sync' => $validated['auto_sync'] ?? false,
            'score_mapping_type' => $validated['score_mapping_type'] ?? 'direct',
            'max_score_override' => $validated['max_score_override'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'CBT link created successfully.',
            'data' => $link->load([
                'cbtExam:id,title,subject_id,class_id',
                'class:id,name',
                'term:id,name',
                'session:id,name',
                'subject:id,name',
            ]),
        ], 201);
    }

    public function importScores(Request $request, string $linkId)
    {
        $this->ensurePermission($request, ['results.enter', 'cbt.view']);

        $user = $request->user();

        $link = CbtAssessmentLink::with(['assessmentComponent', 'cbtExam'])
            ->findOrFail($linkId);

        $this->authorizeComponentForUser($link->assessmentComponent, $user->school_id);

        $query = QuizResult::query()
            ->where('quiz_id', $link->cbt_exam_id)
            ->whereNotNull('marks_obtained')
            ->with(['student:id,first_name,last_name,admission_no,school_class_id,current_session_id,current_term_id']);

        if ($link->class_id) {
            $query->whereHas('student', function ($builder) use ($link) {
                $builder->where('school_class_id', $link->class_id);
            });
        }

        if ($link->session_id) {
            $query->whereHas('student', function ($builder) use ($link) {
                $builder->where('current_session_id', $link->session_id);
            });
        }

        if ($link->term_id) {
            $query->whereHas('student', function ($builder) use ($link) {
                $builder->where('current_term_id', $link->term_id);
            });
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            return response()->json([
                'message' => 'No CBT results found for this exam.',
                'count' => 0,
            ]);
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($results, $link, $user, &$imported, &$skipped) {
            foreach ($results as $result) {
                $existing = CbtScoreImport::query()
                    ->where('cbt_assessment_link_id', $link->id)
                    ->where('student_id', $result->student_id)
                    ->first();

                if ($existing && $existing->status === 'synced') {
                    $skipped++;
                    continue;
                }

                $rawScore = (float) $result->marks_obtained;
                $maxScore = (float) ($result->total_marks ?: 0);

                $convertedScore = $this->convertScore(
                    $rawScore,
                    $maxScore > 0 ? $maxScore : 100,
                    $link->score_mapping_type,
                    $link->max_score_override
                );

                CbtScoreImport::updateOrCreate(
                    [
                        'cbt_assessment_link_id' => $link->id,
                        'student_id' => $result->student_id,
                    ],
                    [
                        'cbt_raw_score' => $rawScore,
                        'cbt_max_score' => $maxScore > 0 ? $maxScore : 100,
                        'converted_score' => $convertedScore,
                        'status' => $link->auto_sync ? 'approved' : 'pending',
                        'approved_by' => $link->auto_sync ? $user->id : null,
                        'approved_at' => $link->auto_sync ? now() : null,
                    ]
                );

                $imported++;
            }

            if ($link->auto_sync) {
                $this->syncApprovedScores($link, $user);
            }
        });

        return response()->json([
            'message' => 'Scores imported successfully.',
            'imported' => $imported,
            'skipped' => $skipped,
            'auto_synced' => $link->auto_sync,
        ]);
    }

    public function pendingScores(Request $request, string $linkId)
    {
        $this->ensurePermission($request, ['results.enter', 'cbt.view']);

        $link = CbtAssessmentLink::with('assessmentComponent')->findOrFail($linkId);

        $this->authorizeComponentForUser($link->assessmentComponent, $request->user()?->school_id);

        $imports = CbtScoreImport::query()
            ->where('cbt_assessment_link_id', $linkId)
            ->with(['student:id,first_name,last_name,admission_no'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'link' => $link,
            'imports' => $imports,
        ]);
    }

    public function approveScores(Request $request, string $linkId)
    {
        $this->ensurePermission($request, ['results.enter', 'cbt.view']);

        $validated = $request->validate([
            'import_ids' => ['required', 'array', 'min:1'],
            'import_ids.*' => ['uuid', 'exists:cbt_score_imports,id'],
        ]);

        $user = $request->user();

        $link = CbtAssessmentLink::with('assessmentComponent')->findOrFail($linkId);

        $this->authorizeComponentForUser($link->assessmentComponent, $user->school_id);

        DB::transaction(function () use ($validated, $link, $user) {
            CbtScoreImport::query()
                ->whereIn('id', $validated['import_ids'])
                ->where('cbt_assessment_link_id', $link->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);

            $this->syncApprovedScores($link, $user);
        });

        return response()->json([
            'message' => 'Scores approved and synced successfully.',
        ]);
    }

    public function rejectScores(Request $request, string $linkId)
    {
        $this->ensurePermission($request, ['results.enter', 'cbt.view']);

        $validated = $request->validate([
            'import_ids' => ['required', 'array', 'min:1'],
            'import_ids.*' => ['uuid', 'exists:cbt_score_imports,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $link = CbtAssessmentLink::with('assessmentComponent')->findOrFail($linkId);

        $this->authorizeComponentForUser($link->assessmentComponent, $user->school_id);

        CbtScoreImport::query()
            ->whereIn('id', $validated['import_ids'])
            ->where('cbt_assessment_link_id', $linkId)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'rejected_reason' => $validated['reason'] ?? 'Rejected by admin',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

        return response()->json([
            'message' => 'Scores rejected.',
        ]);
    }

    public function destroy(Request $request, string $linkId)
    {
        $this->ensurePermission($request, ['results.enter', 'cbt.view']);

        $link = CbtAssessmentLink::with('assessmentComponent')->findOrFail($linkId);

        $this->authorizeComponentForUser($link->assessmentComponent, $request->user()?->school_id);

        $syncedCount = $link->imports()->where('status', 'synced')->count();
        if ($syncedCount > 0) {
            return response()->json([
                'message' => 'Cannot delete link with synced scores. Remove synced scores first.',
            ], 422);
        }

        $link->delete();

        return response()->json([
            'message' => 'CBT link deleted successfully.',
        ]);
    }

    private function authorizeComponentForUser(?AssessmentComponent $component, ?string $schoolId): void
    {
        if (! $component || ! $schoolId || $component->school_id !== $schoolId) {
            abort(404, 'Assessment component not found.');
        }
    }

    private function convertScore(float $rawScore, float $maxScore, string $mappingType, ?float $override = null): float
    {
        if ($maxScore <= 0) {
            return 0.0;
        }

        $targetMax = $override ?? $maxScore;

        $converted = match ($mappingType) {
            'percentage' => ($rawScore / $maxScore) * 100,
            'scaled' => ($rawScore / $maxScore) * $targetMax,
            default => $rawScore,
        };

        return round($converted, 2);
    }

    private function syncApprovedScores(CbtAssessmentLink $link, $actor): void
    {
        $component = $link->assessmentComponent;
        $quiz = $link->cbtExam;

        if (! $component || ! $quiz) {
            throw ValidationException::withMessages([
                'link' => ['CBT link is missing required relationships.'],
            ]);
        }

        $subjectId = $link->subject_id ?? $quiz->subject_id;
        if (! $subjectId) {
            throw ValidationException::withMessages([
                'subject_id' => ['Subject is required to sync CBT scores.'],
            ]);
        }

        $componentSubjectIds = $component->subjects()->pluck('subjects.id');
        if ($componentSubjectIds->isNotEmpty() && ! $componentSubjectIds->contains($subjectId)) {
            throw ValidationException::withMessages([
                'subject_id' => ['Assessment component is not attached to the selected subject.'],
            ]);
        }

        $approvedImports = CbtScoreImport::query()
            ->where('cbt_assessment_link_id', $link->id)
            ->where('status', 'approved')
            ->with(['student:id,school_id,school_class_id,current_session_id,current_term_id'])
            ->get();

        $schoolId = $component->school_id;

        foreach ($approvedImports as $import) {
            $student = $import->student;
            if (! $student || $student->school_id !== $schoolId) {
                continue;
            }

            if ($link->class_id && $student->school_class_id !== $link->class_id) {
                continue;
            }

            $termId = $link->term_id ?: $student->current_term_id;
            if (! $termId) {
                throw ValidationException::withMessages([
                    'term_id' => ['Term is required to sync CBT scores.'],
                ]);
            }

            $sessionId = $link->session_id ?: $student->current_session_id;
            $term = Term::query()
                ->where('school_id', $schoolId)
                ->where('id', $termId)
                ->first();

            if (! $term) {
                throw ValidationException::withMessages([
                    'term_id' => ["Selected term {$termId} is not available for this school."],
                ]);
            }

            if (! $sessionId) {
                $sessionId = $term->session_id;
            }

            $session = Session::query()
                ->where('school_id', $schoolId)
                ->where('id', $sessionId)
                ->first();

            if (! $session) {
                throw ValidationException::withMessages([
                    'session_id' => ["Selected session {$sessionId} is not available for this school."],
                ]);
            }

            if ($term->session_id !== $session->id) {
                throw ValidationException::withMessages([
                    'session_id' => ['The selected term does not belong to the specified session.'],
                ]);
            }

            $subject = Subject::query()
                ->where('school_id', $schoolId)
                ->where('id', $subjectId)
                ->first();

            if (! $subject) {
                throw ValidationException::withMessages([
                    'subject_id' => ['Selected subject is not available for this school.'],
                ]);
            }

            $convertedScore = $import->converted_score;
            if ($convertedScore === null) {
                $convertedScore = $this->convertScore(
                    (float) $import->cbt_raw_score,
                    (float) $import->cbt_max_score,
                    $link->score_mapping_type,
                    $link->max_score_override
                );
            }

            $result = Result::query()
                ->where('student_id', $import->student_id)
                ->where('subject_id', $subjectId)
                ->where('assessment_component_id', $link->assessment_component_id)
                ->where('term_id', $termId)
                ->where('session_id', $sessionId)
                ->first();

            if ($result) {
                $result->total_score = (float) $convertedScore;
                $result->save();
            } else {
                Result::create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $import->student_id,
                    'subject_id' => $subjectId,
                    'assessment_component_id' => $link->assessment_component_id,
                    'term_id' => $termId,
                    'session_id' => $sessionId,
                    'total_score' => (float) $convertedScore,
                ]);
            }

            $import->update([
                'status' => 'synced',
                'approved_by' => $import->approved_by ?? $actor?->id,
                'approved_at' => $import->approved_at ?? now(),
            ]);
        }
    }
}
