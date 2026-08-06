<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentStructure;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentComponentStructureController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all structures for a specific assessment component
     */
    public function indexByComponent($assessmentComponentId): JsonResponse
    {
        $user = auth()->user();

        $component = AssessmentComponent::where('school_id', $user->school_id)
            ->findOrFail($assessmentComponentId);

        $structures = AssessmentComponentStructure::where('school_id', $user->school_id)
            ->where('assessment_component_id', $assessmentComponentId)
            ->with(['class', 'term'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'component' => $component,
            'structures' => $structures,
        ]);
    }

    /**
     * Create or update a structure
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'assessment_component_id' => 'required|uuid|exists:assessment_components,id',
            'class_id' => 'nullable|uuid|exists:classes,id',
            'term_id' => 'nullable|uuid|exists:terms,id',
            'max_score' => 'required|numeric|min:0|max:1000',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        // Ensure component belongs to user's school
        AssessmentComponent::where('school_id', $user->school_id)
            ->findOrFail($validated['assessment_component_id']);

        $structure = AssessmentComponentStructure::updateOrCreate(
            [
                'assessment_component_id' => $validated['assessment_component_id'],
                'class_id' => $validated['class_id'] ?? null,
                'term_id' => $validated['term_id'] ?? null,
            ],
            array_merge($validated, ['school_id' => $user->school_id])
        );

        return response()->json(
            $structure->load(['class', 'term']),
            201
        );
    }

    /**
     * Get max score for a specific assessment component, class, and term
     * Returns the highest priority applicable score
     */
    public function getMaxScore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assessment_component_id' => 'required|uuid',
            'class_id' => 'nullable|uuid',
            'term_id' => 'nullable|uuid',
        ]);

        $maxScore = AssessmentComponentStructure::getMaxScore(
            $validated['assessment_component_id'],
            $validated['class_id'] ?? null,
            $validated['term_id'] ?? null
        );

        return response()->json([
            'max_score' => $maxScore,
            'assessment_component_id' => $validated['assessment_component_id'],
            'class_id' => $validated['class_id'] ?? null,
            'term_id' => $validated['term_id'] ?? null,
        ]);
    }

    /**
     * Get all applicable structures for a component and class
     */
    public function getApplicable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assessment_component_id' => 'required|uuid',
            'class_id' => 'nullable|uuid',
            'term_id' => 'nullable|uuid',
        ]);

        $structures = AssessmentComponentStructure::getApplicableStructures(
            $validated['assessment_component_id'],
            $validated['class_id'] ?? null,
            $validated['term_id'] ?? null
        );

        return response()->json([
            'structures' => $structures,
        ]);
    }

    /**
     * Delete a structure
     */
    public function destroy($id): JsonResponse
    {
        $user = auth()->user();

        $structure = AssessmentComponentStructure::where('school_id', $user->school_id)
            ->findOrFail($id);

        $structure->delete();

        return response()->json(null, 204);
    }

    /**
     * Update a structure
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();

        $structure = AssessmentComponentStructure::where('school_id', $user->school_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'assessment_component_id' => 'required|uuid|exists:assessment_components,id',
            'class_id' => 'nullable|uuid|exists:classes,id',
            'term_id' => 'nullable|uuid|exists:terms,id',
            'max_score' => 'required|numeric|min:0|max:1000',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        AssessmentComponent::where('school_id', $user->school_id)
            ->findOrFail($validated['assessment_component_id']);

        $duplicate = AssessmentComponentStructure::where('school_id', $user->school_id)
            ->where('assessment_component_id', $validated['assessment_component_id'])
            ->where('class_id', $validated['class_id'] ?? null)
            ->where('term_id', $validated['term_id'] ?? null)
            ->where('id', '!=', $structure->id)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'A structure for this class and term already exists.',
            ], 422);
        }

        $structure->update($validated);

        return response()->json($structure->fresh()->load(['class', 'term']));
    }

    /**
     * Bulk create/update structures
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'structures' => 'required|array|min:1',
            'structures.*.assessment_component_id' => 'required|uuid|exists:assessment_components,id',
            'structures.*.class_id' => 'nullable|uuid|exists:classes,id',
            'structures.*.term_id' => 'nullable|uuid|exists:terms,id',
            'structures.*.max_score' => 'required|numeric|min:0|max:1000',
            'structures.*.description' => 'nullable|string|max:1000',
            'structures.*.is_active' => 'boolean',
        ]);

        $created = [];

        foreach ($validated['structures'] as $data) {
            $structure = AssessmentComponentStructure::updateOrCreate(
                [
                    'assessment_component_id' => $data['assessment_component_id'],
                    'class_id' => $data['class_id'] ?? null,
                    'term_id' => $data['term_id'] ?? null,
                ],
                array_merge($data, ['school_id' => $user->school_id])
            );

            $created[] = $structure->load(['class', 'term']);
        }

        return response()->json(['structures' => $created], 201);
    }
}
