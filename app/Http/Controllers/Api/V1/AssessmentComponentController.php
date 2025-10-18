<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\Result;
use App\Models\Session;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssessmentComponentController extends Controller
{
    public function index(Request $request)
    {
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $perPage = max((int) $request->input('per_page', 50), 1);

        $query = AssessmentComponent::query()
            ->where('school_id', $school->id)
            ->with([
                'subjects:id,name,code',
                'session:id,name',
                'term:id,name',
            ]);

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->input('session_id'));
        }

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->input('term_id'));
        }

        if ($request->filled('subject_id')) {
            $subjectId = $request->input('subject_id');
            $query->whereHas('subjects', function ($query) use ($subjectId) {
                $query->where('subjects.id', $subjectId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%");
            });
        }

        $query->orderBy('order')->orderBy('name');

        $components = $query->paginate($perPage)->withQueryString();

        return response()->json($components);
    }

    public function store(Request $request)
    {
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'gt:0'],
            'order' => ['required', 'integer', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['uuid'],
        ]);

        $subjectIds = array_values(array_unique(array_filter($validated['subject_ids'])));

        if (empty($subjectIds)) {
            return response()->json([
                'message' => 'Please select at least one subject.',
            ], 422);
        }

        $this->ensureContextBelongsToSchool(
            $school->id,
            $validated['session_id'],
            $validated['term_id'],
            $subjectIds
        );

        $this->ensureUniqueName(
            $school->id,
            $validated['session_id'],
            $validated['term_id'],
            $validated['name']
        );

        $component = DB::transaction(function () use ($school, $validated, $subjectIds) {
            /** @var AssessmentComponent $component */
            $component = AssessmentComponent::create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'session_id' => $validated['session_id'],
                'term_id' => $validated['term_id'],
                'name' => $validated['name'],
                'weight' => $validated['weight'],
                'order' => $validated['order'],
                'label' => $validated['label'] ?? null,
            ]);

            $component->subjects()->sync($subjectIds);

            return $component->fresh(['subjects:id,name,code', 'session:id,name', 'term:id,name']);
        });

        return response()->json([
            'message' => 'Assessment component created successfully.',
            'data' => $component,
        ], 201);
    }

    public function show(Request $request, AssessmentComponent $assessmentComponent)
    {
        $this->authorizeComponent($request, $assessmentComponent);

        return response()->json(
            $assessmentComponent->load(['subjects:id,name,code', 'session:id,name', 'term:id,name'])
        );
    }

    public function update(Request $request, AssessmentComponent $assessmentComponent)
    {
        $this->authorizeComponent($request, $assessmentComponent);

        if ($this->hasDependentResults($assessmentComponent->id)) {
            return response()->json([
                'message' => 'Cannot update assessment component because results already reference it.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'weight' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'order' => ['sometimes', 'required', 'integer', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
            'session_id' => ['sometimes', 'required', 'uuid'],
            'term_id' => ['sometimes', 'required', 'uuid'],
            'subject_ids' => ['sometimes', 'array', 'min:1'],
            'subject_ids.*' => ['uuid'],
        ]);

        $component = $assessmentComponent;

        $sessionId = $validated['session_id'] ?? $component->session_id;
        $termId = $validated['term_id'] ?? $component->term_id;
        $subjectIds = array_key_exists('subject_ids', $validated)
            ? array_values(array_unique(array_filter($validated['subject_ids'])))
            : $component->subjects()->pluck('subjects.id')->all();

        if (empty($subjectIds)) {
            return response()->json([
                'message' => 'Please select at least one subject.',
            ], 422);
        }

        $this->ensureContextBelongsToSchool(
            $component->school_id,
            $sessionId,
            $termId,
            $subjectIds
        );

        $this->ensureUniqueName(
            $component->school_id,
            $sessionId,
            $termId,
            $validated['name'] ?? $component->name,
            $component->id
        );

        $componentData = $validated;
        unset($componentData['subject_ids']);

        $component->fill($componentData);

        if ($component->isDirty()) {
            $component->save();
        }

        if (array_key_exists('subject_ids', $validated)) {
            $component->subjects()->sync($subjectIds);
        }

        return response()->json([
            'message' => 'Assessment component updated successfully.',
            'data' => $component->fresh(['subjects:id,name,code', 'session:id,name', 'term:id,name']),
        ]);
    }

    public function destroy(Request $request, AssessmentComponent $assessmentComponent)
    {
        $this->authorizeComponent($request, $assessmentComponent);

        if ($this->hasDependentResults($assessmentComponent->id)) {
            return response()->json([
                'message' => 'Cannot delete assessment component because results already reference it.',
            ], 422);
        }

        $assessmentComponent->delete();

        return response()->json([
            'message' => 'Assessment component deleted successfully.',
        ]);
    }

    private function ensureContextBelongsToSchool(string $schoolId, string $sessionId, string $termId, array $subjectIds): void
    {
        $sessionExists = Session::where('id', $sessionId)
            ->where('school_id', $schoolId)
            ->exists();

        abort_unless($sessionExists, 404, 'Session not found for the authenticated school.');

        $termExists = Term::where('id', $termId)
            ->where('school_id', $schoolId)
            ->where('session_id', $sessionId)
            ->exists();

        abort_unless($termExists, 404, 'Term not found for the authenticated school.');

        if (! empty($subjectIds)) {
            $subjectsCount = Subject::whereIn('id', $subjectIds)
                ->where('school_id', $schoolId)
                ->count();

            abort_unless($subjectsCount === count($subjectIds), 404, 'One or more subjects were not found for the authenticated school.');
        }
    }

    private function ensureUniqueName(string $schoolId, string $sessionId, string $termId, string $name, ?string $ignoreId = null): void
    {
        $rule = Rule::unique('assessment_components')->where(function ($query) use ($schoolId, $sessionId, $termId) {
            return $query
                ->where('school_id', $schoolId)
                ->where('session_id', $sessionId)
                ->where('term_id', $termId);
        });

        if ($ignoreId) {
            $rule->ignore($ignoreId);
        }

        Validator::make(['name' => $name], ['name' => [$rule]])->validate();
    }

    private function hasDependentResults(string $componentId): bool
    {
        return Result::where('assessment_component_id', $componentId)->exists();
    }

    private function authorizeComponent(Request $request, AssessmentComponent $component): void
    {
        $schoolId = optional($request->user()->school)->id;

        abort_unless($schoolId && $component->school_id === $schoolId, 404);
    }
}
