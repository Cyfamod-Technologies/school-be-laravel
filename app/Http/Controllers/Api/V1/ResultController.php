<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\Result;
use App\Models\Session;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResultController extends Controller
{
    public function index(Request $request)
    {
        $school = optional($request->user())->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $perPage = max((int) $request->input('per_page', 50), 1);

        $query = Result::query()
            ->with([
                'student:id,first_name,last_name,middle_name,admission_no,school_class_id,class_arm_id,class_section_id,current_session_id,current_term_id',
                'student.school_class:id,name',
                'student.class_arm:id,name',
                'student.class_section:id,name',
                'subject:id,name,code',
                'session:id,name',
                'term:id,name,session_id',
                'assessment_component:id,name,label,order,weight',
            ])
            ->whereHas('student', function (Builder $builder) use ($school) {
                $builder->where('school_id', $school->id);
            });

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->input('session_id'));
        }

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->input('term_id'));
        }

        if ($request->filled('assessment_component_id')) {
            $componentId = $request->input('assessment_component_id');
            if ($componentId === 'none' || $componentId === 'null') {
                $query->whereNull('assessment_component_id');
            } else {
                $query->where('assessment_component_id', $componentId);
            }
        }

        if ($request->filled('school_class_id')) {
            $classId = $request->input('school_class_id');
            $query->whereHas('student', fn (Builder $builder) => $builder->where('school_class_id', $classId));
        }

        if ($request->filled('class_arm_id')) {
            $armId = $request->input('class_arm_id');
            $query->whereHas('student', fn (Builder $builder) => $builder->where('class_arm_id', $armId));
        }

        if ($request->filled('class_section_id')) {
            $sectionId = $request->input('class_section_id');
            $query->whereHas('student', fn (Builder $builder) => $builder->where('class_section_id', $sectionId));
        }

        if ($request->filled('min_score')) {
            $query->where('total_score', '>=', (float) $request->input('min_score'));
        }

        if ($request->filled('max_score')) {
            $query->where('total_score', '<=', (float) $request->input('max_score'));
        }

        $query->orderByDesc('updated_at');

        $results = $query->paginate($perPage)->withQueryString();

        return response()->json($results);
    }

    public function batchUpsert(Request $request)
    {
        $school = optional($request->user())->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'session_id' => ['nullable', 'uuid'],
            'term_id' => ['nullable', 'uuid'],
            'assessment_component_id' => ['nullable', 'uuid'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'uuid'],
            'entries.*.subject_id' => ['required', 'uuid'],
            'entries.*.score' => ['required', 'numeric', 'between:0,100'],
            'entries.*.remarks' => ['nullable', 'string', 'max:1000'],
            'entries.*.assessment_component_id' => ['nullable', 'uuid'],
            'entries.*.session_id' => ['nullable', 'uuid'],
            'entries.*.term_id' => ['nullable', 'uuid'],
        ]);

        $defaultSessionId = $validated['session_id'] ?? $school->current_session_id;
        $defaultTermId = $validated['term_id'] ?? $school->current_term_id;
        $defaultComponentId = $validated['assessment_component_id'] ?? null;

        $entries = collect($validated['entries']);

        $studentIds = $entries->pluck('student_id')->unique()->values();
        $subjectIds = $entries->pluck('subject_id')->unique()->values();
        $sessionIds = $entries->pluck('session_id')->filter()->values();
        $termIds = $entries->pluck('term_id')->filter()->values();
        $componentIds = $entries->pluck('assessment_component_id')->filter()->values();

        if ($defaultSessionId) {
            $sessionIds->push($defaultSessionId);
        }

        if ($defaultTermId) {
            $termIds->push($defaultTermId);
        }

        if ($defaultComponentId) {
            $componentIds->push($defaultComponentId);
        }

        $sessions = $sessionIds->isEmpty()
            ? collect()
            : Session::query()
                ->where('school_id', $school->id)
                ->whereIn('id', $sessionIds->unique())
                ->get()
                ->keyBy('id');

        $terms = $termIds->isEmpty()
            ? collect()
            : Term::query()
                ->where('school_id', $school->id)
                ->whereIn('id', $termIds->unique())
                ->get()
                ->keyBy('id');

        $students = Student::query()
            ->where('school_id', $school->id)
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        if ($students->count() !== $studentIds->count()) {
            $missing = $studentIds->diff($students->keys());
            throw ValidationException::withMessages([
                'entries' => ['One or more students were not found for this school: ' . $missing->implode(', ')],
            ]);
        }

        $subjects = Subject::query()
            ->where('school_id', $school->id)
            ->whereIn('id', $subjectIds)
            ->get()
            ->keyBy('id');

        if ($subjects->count() !== $subjectIds->count()) {
            $missing = $subjectIds->diff($subjects->keys());
            throw ValidationException::withMessages([
                'entries' => ['One or more subjects were not found for this school: ' . $missing->implode(', ')],
            ]);
        }

        $components = $componentIds->isEmpty()
            ? collect()
            : AssessmentComponent::query()
                ->where('school_id', $school->id)
                ->whereIn('id', $componentIds->unique())
                ->with(['subjects' => function ($query) {
                    $query->select('subjects.id', 'subjects.name', 'subjects.code');
                }])
                ->get()
                ->keyBy('id');

        if ($componentIds->isNotEmpty() && $components->count() !== $componentIds->unique()->count()) {
            $missing = $componentIds->unique()->diff($components->keys());
            throw ValidationException::withMessages([
                'assessment_component_id' => ['One or more assessment components were not found for this school: ' . $missing->implode(', ')],
            ]);
        }

        $created = 0;
        $updated = 0;
        $savedResults = collect();

        DB::transaction(function () use (
            $entries,
            $defaultSessionId,
            $defaultTermId,
            $defaultComponentId,
            $sessions,
            $terms,
            $students,
            $subjects,
            $components,
            $school,
            &$created,
            &$updated,
            &$savedResults
        ) {
            foreach ($entries as $entry) {
                $student = $students->get($entry['student_id']);
                $subject = $subjects->get($entry['subject_id']);

                $entrySessionId = $entry['session_id'] ?? $defaultSessionId;
                $entryTermId = $entry['term_id'] ?? $defaultTermId;
                $entryComponentId = $entry['assessment_component_id'] ?? $defaultComponentId;

                $term = $entryTermId ? $terms->get($entryTermId) : null;

                if ($term === null && $entryTermId !== null) {
                    throw ValidationException::withMessages([
                        'entries' => ["Selected term {$entryTermId} is not available for this school."],
                    ]);
                }

                if (! $entrySessionId && $term) {
                    $entrySessionId = $term->session_id;
                }

                if (! $entrySessionId) {
                    throw ValidationException::withMessages([
                        'entries' => ['Session is required for each score entry.'],
                    ]);
                }

                $session = $sessions->get($entrySessionId);
                if (! $session) {
                    $session = Session::query()
                        ->where('school_id', $school->id)
                        ->where('id', $entrySessionId)
                        ->first();

                    if (! $session) {
                        throw ValidationException::withMessages([
                            'entries' => ["Selected session {$entrySessionId} is not available for this school."],
                        ]);
                    }

                    $sessions->put($session->id, $session);
                }

                if ($term && $term->session_id !== $session->id) {
                    throw ValidationException::withMessages([
                        'entries' => ['The selected term does not belong to the specified session.'],
                    ]);
                }

                if ($entryComponentId) {
                    $component = $components->get($entryComponentId);

                    if (! $component) {
                        throw ValidationException::withMessages([
                            'entries' => ["Assessment component {$entryComponentId} is not available for this school."],
                        ]);
                    }

                    $componentSubjectIds = $component->subjects->pluck('id')->map(fn ($id) => (string) $id);
                    if ($componentSubjectIds->isNotEmpty() && ! $componentSubjectIds->contains($subject->id)) {
                        throw ValidationException::withMessages([
                            'entries' => ['The selected assessment component is not attached to the chosen subject.'],
                        ]);
                    }
                }

                if (! $entryTermId) {
                    throw ValidationException::withMessages([
                        'entries' => ['Term is required for each score entry.'],
                    ]);
                }

                $query = Result::query()
                    ->where('student_id', $student->id)
                    ->where('subject_id', $subject->id)
                    ->where('session_id', $entrySessionId)
                    ->where('term_id', $entryTermId);

                if ($entryComponentId) {
                    $query->where('assessment_component_id', $entryComponentId);
                } else {
                    $query->whereNull('assessment_component_id');
                }

                /** @var Result|null $result */
                $result = $query->first();

                $payload = [
                    'total_score' => $entry['score'],
                    'remarks' => array_key_exists('remarks', $entry) && $entry['remarks'] !== '' ? $entry['remarks'] : null,
                ];

                if ($result) {
                    $result->fill($payload);
                    if ($result->isDirty()) {
                        $result->save();
                        $updated++;
                    }
                } else {
                    $result = Result::create([
                        'id' => (string) Str::uuid(),
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'session_id' => $entrySessionId,
                        'term_id' => $entryTermId,
                        'assessment_component_id' => $entryComponentId,
                    ] + $payload);
                    $created++;
                }

                $savedResults->push(
                    $result->fresh([
                        'student:id,first_name,last_name,middle_name,admission_no,school_class_id,class_arm_id,class_section_id',
                        'student.school_class:id,name',
                        'student.class_arm:id,name',
                        'student.class_section:id,name',
                        'subject:id,name,code',
                        'session:id,name',
                        'term:id,name',
                        'assessment_component:id,name,label,order,weight',
                    ])
                );
            }
        });

        return response()->json([
            'message' => 'Scores saved successfully.',
            'meta' => [
                'created' => $created,
                'updated' => $updated,
                'total' => $savedResults->count(),
            ],
            'data' => $savedResults,
        ]);
    }
}
