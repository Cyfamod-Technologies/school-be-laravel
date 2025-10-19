<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\Result;
use App\Models\Subject;
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
            ]);

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
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['uuid'],
        ]);

        $subjectIds = array_values(array_unique(array_filter($validated['subject_ids'])));

        if (empty($subjectIds)) {
            return response()->json([
                'message' => 'Please select at least one subject.',
            ], 422);
        }

        $this->ensureSubjectsBelongToSchool($school->id, $subjectIds);

        $this->ensureUniqueName($school->id, $validated['name']);

        $component = DB::transaction(function () use ($school, $validated, $subjectIds) {
            /** @var AssessmentComponent $component */
            $component = AssessmentComponent::create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'name' => $validated['name'],
                'weight' => $validated['weight'],
                'order' => $validated['order'],
                'label' => $validated['label'] ?? null,
            ]);

            $component->subjects()->sync($subjectIds);

            return $component->fresh(['subjects:id,name,code']);
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
            $assessmentComponent->load(['subjects:id,name,code'])
        );
    }

    public function update(Request $request, AssessmentComponent $assessmentComponent)
    {
        $this->authorizeComponent($request, $assessmentComponent);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'weight' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'order' => ['sometimes', 'required', 'integer', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
            'subject_ids' => ['sometimes', 'array', 'min:1'],
            'subject_ids.*' => ['uuid'],
        ]);

        $component = $assessmentComponent;

        $subjectIds = array_key_exists('subject_ids', $validated)
            ? array_values(array_unique(array_filter($validated['subject_ids'])))
            : $component->subjects()->pluck('subjects.id')->all();

        if (empty($subjectIds)) {
            return response()->json([
                'message' => 'Please select at least one subject.',
            ], 422);
        }

        $this->ensureSubjectsBelongToSchool($component->school_id, $subjectIds);

        $this->ensureUniqueName(
            $component->school_id,
            $validated['name'] ?? $component->name,
            $component->id
        );

        $componentData = $validated;
        unset($componentData['subject_ids']);

        $updatedComponent = DB::transaction(function () use ($component, $componentData, $validated, $subjectIds) {
            $component->fill($componentData);

            if ($component->isDirty()) {
                $component->save();
            }

            if (array_key_exists('subject_ids', $validated)) {
                $component->subjects()->sync($subjectIds);
            }

            return $component->fresh(['subjects:id,name,code']);
        });

        return response()->json([
            'message' => 'Assessment component updated successfully.',
            'data' => $updatedComponent,
        ]);
    }

    public function destroy(Request $request, AssessmentComponent $assessmentComponent)
    {
        $this->authorizeComponent($request, $assessmentComponent);

        DB::transaction(function () use ($assessmentComponent) {
            Result::query()
                ->where('assessment_component_id', $assessmentComponent->id)
                ->update([
                    'assessment_component_id' => null,
                    'component_slot' => Result::NULL_COMPONENT_UUID,
                ]);

            $assessmentComponent->delete();
        });

        return response()->json([
            'message' => 'Assessment component deleted successfully.',
        ]);
    }

    private function ensureSubjectsBelongToSchool(string $schoolId, array $subjectIds): void
    {
        if (! empty($subjectIds)) {
            $subjectsCount = Subject::whereIn('id', $subjectIds)
                ->where('school_id', $schoolId)
                ->count();

            abort_unless($subjectsCount === count($subjectIds), 404, 'One or more subjects were not found for the authenticated school.');
        }
    }

    private function ensureUniqueName(string $schoolId, string $name, ?string $ignoreId = null): void
    {
        $rule = Rule::unique('assessment_components')->where(function ($query) use ($schoolId) {
            return $query
                ->where('school_id', $schoolId);
        });

        if ($ignoreId) {
            $rule->ignore($ignoreId);
        }

        Validator::make(['name' => $name], ['name' => [$rule]])->validate();
    }

    private function authorizeComponent(Request $request, AssessmentComponent $component): void
    {
        $schoolId = optional($request->user()->school)->id;

        abort_unless($schoolId && $component->school_id === $schoolId, 404);
    }
}
