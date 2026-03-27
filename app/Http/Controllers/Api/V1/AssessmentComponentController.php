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

/**
 * @OA\Tag(
 *     name="school-v1.9",
 *     description="v1.9 – Results, Components, Grading & Skills"
 * )
 */
class AssessmentComponentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/settings/assessment-components",
     *     tags={"school-v1.9"},
     *     summary="List assessment components",
     *     @OA\Parameter(name="subject_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Components returned"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/settings/assessment-components",
     *     tags={"school-v1.9"},
     *     summary="Create assessment component",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","weight","order","subject_ids"},
     *             @OA\Property(property="name", type="string", example="CA Test"),
     *             @OA\Property(property="weight", type="number", example=20),
     *             @OA\Property(property="order", type="integer", example=1),
     *             @OA\Property(property="label", type="string", example="CAT"),
     *             @OA\Property(property="subject_ids", type="array", @OA\Items(type="string", format="uuid"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Component created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/v1/settings/assessment-components/{id}",
     *     tags={"school-v1.9"},
     *     summary="Get assessment component",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Component returned"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, AssessmentComponent $assessmentComponent)
    {
        $this->authorizeComponent($request, $assessmentComponent);

        return response()->json(
            $assessmentComponent->load(['subjects:id,name,code'])
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/settings/assessment-components/{id}",
     *     tags={"school-v1.9"},
     *     summary="Update assessment component",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="weight", type="number"),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="label", type="string"),
     *             @OA\Property(property="subject_ids", type="array", @OA\Items(type="string", format="uuid"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Component updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/v1/settings/assessment-components/{id}",
     *     tags={"school-v1.9"},
     *     summary="Delete assessment component",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Component deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, AssessmentComponent $assessmentComponent)
    {
        $this->authorizeComponent($request, $assessmentComponent);

        $linkedResultsCount = Result::query()
            ->where('assessment_component_id', $assessmentComponent->id)
            ->count();

        if ($linkedResultsCount > 0) {
            return response()->json([
                'message' => 'This assessment component cannot be deleted because results are already linked to it.',
                'linked_results_count' => $linkedResultsCount,
            ], 422);
        }

        $assessmentComponent->delete();

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
