<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SkillCategory;
use App\Models\SkillType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="school-v1.9",
 *     description="v1.9 – Results, Components, Grading & Skills"
 * )
 */
class SkillCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/settings/skill-categories",
     *     tags={"school-v1.9"},
     *     summary="List skill categories",
     *
     *     @OA\Response(response=200, description="Categories returned"),
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

        $categories = SkillCategory::query()
            ->where('school_id', $school->id)
            ->with(['skill_types' => function ($query) {
                $query->orderBy('name')
                    ->select(['id', 'skill_category_id', 'name', 'description', 'weight']);
            }])
            ->orderBy('name')
            ->get()
            ->map(function (SkillCategory $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'skill_types' => $category->skill_types->map(function (SkillType $type) {
                        return [
                            'id' => $type->id,
                            'name' => $type->name,
                            'description' => $type->description,
                            'weight' => $type->weight,
                            'skill_category_id' => $type->skill_category_id,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json(['data' => $categories]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/settings/skill-categories",
     *     tags={"school-v1.9"},
     *     summary="Create skill category",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name"},
     *
     *             @OA\Property(property="name", type="string", example="Affective Skills"),
     *             @OA\Property(property="description", type="string", example="Behavioural attributes")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Category created"),
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
            'name' => ['required', 'string', 'max:100', Rule::unique('skill_categories', 'name')->where('school_id', $school->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category = SkillCategory::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Skill category created successfully.',
            'data' => $category,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/settings/skill-categories/{id}",
     *     tags={"school-v1.9"},
     *     summary="Update skill category",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Category updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, SkillCategory $skillCategory)
    {
        $this->authorizeCategory($request, $skillCategory);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('skill_categories', 'name')->ignore($skillCategory->id)->where('school_id', $skillCategory->school_id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $skillCategory->fill($validated);

        if ($skillCategory->isDirty()) {
            $skillCategory->save();
        }

        return response()->json([
            'message' => 'Skill category updated successfully.',
            'data' => $skillCategory->fresh(),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/settings/skill-categories/{id}",
     *     tags={"school-v1.9"},
     *     summary="Delete skill category",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Category deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, SkillCategory $skillCategory)
    {
        $this->authorizeCategory($request, $skillCategory);

        $skillCategory->delete();

        return response()->json([
            'message' => 'Skill category deleted successfully.',
        ]);
    }

    private function authorizeCategory(Request $request, SkillCategory $skillCategory): void
    {
        $user = $request->user();

        if (! $user || $user->school_id !== $skillCategory->school_id) {
            abort(403, 'You are not allowed to manage this skill category.');
        }
    }
}
