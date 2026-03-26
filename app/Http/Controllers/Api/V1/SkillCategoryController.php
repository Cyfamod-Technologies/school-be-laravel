<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SkillCategory;
use App\Models\SkillType;
use App\Support\SkillScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        $classId = SkillScope::normalizeClassId($request->input('school_class_id'));

        $categories = SkillScope::applyCategoryVisibility(
                SkillCategory::query(),
                $school,
                $classId
            )
            ->with([
                'school_class:id,name',
                'skill_types' => function ($query) use ($school, $classId) {
                    SkillScope::applyTypeVisibility($query, $school, $classId)
                        ->orderBy('name')
                        ->select([
                            'id',
                            'skill_category_id',
                            'school_id',
                            'school_class_id',
                            'name',
                            'description',
                            'weight',
                        ]);
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(function (SkillCategory $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'school_class_id' => $category->school_class_id,
                    'school_class' => $category->school_class
                        ? [
                            'id' => $category->school_class->id,
                            'name' => $category->school_class->name,
                        ]
                        : null,
                    'skill_types' => $category->skill_types->map(function (SkillType $type) {
                        return [
                            'id' => $type->id,
                            'name' => $type->name,
                            'description' => $type->description,
                            'weight' => $type->weight,
                            'skill_category_id' => $type->skill_category_id,
                            'school_class_id' => $type->school_class_id,
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
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Affective Skills"),
     *             @OA\Property(property="description", type="string", example="Behavioural attributes")
     *         )
     *     ),
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
            'name' => ['required', 'string', 'max:100', 'regex:/.*\S.*/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'school_class_id' => ['nullable', 'uuid'],
        ]);

        $classId = $this->resolveCategoryClassId($school, $validated['school_class_id'] ?? null);
        $name = trim($validated['name']);

        $this->assertUniqueCategoryName($school->id, $name, $classId);

        $category = SkillCategory::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'school_class_id' => $classId,
            'name' => $name,
            'description' => $validated['description'] ?? null,
        ])->load('school_class:id,name');

        return response()->json([
            'message' => 'Skill category created successfully.',
            'data' => $this->transformCategory($category),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/settings/skill-categories/{id}",
     *     tags={"school-v1.9"},
     *     summary="Update skill category",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Category updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, SkillCategory $skillCategory)
    {
        $this->authorizeCategory($request, $skillCategory);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', 'regex:/.*\S.*/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'school_class_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $school = $request->user()->school;

        if (array_key_exists('name', $validated)) {
            $skillCategory->name = trim($validated['name']);
        }

        if (array_key_exists('description', $validated)) {
            $skillCategory->description = $validated['description'] ?? null;
        }

        if (array_key_exists('school_class_id', $validated)) {
            $skillCategory->school_class_id = $this->resolveCategoryClassId($school, $validated['school_class_id']);
        }

        $this->assertUniqueCategoryName(
            $skillCategory->school_id,
            $skillCategory->name,
            $skillCategory->school_class_id,
            $skillCategory->id
        );

        if ($skillCategory->isDirty()) {
            $skillCategory->save();
        }

        return response()->json([
            'message' => 'Skill category updated successfully.',
            'data' => $this->transformCategory($skillCategory->fresh('school_class:id,name')),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/settings/skill-categories/{id}",
     *     tags={"school-v1.9"},
     *     summary="Delete skill category",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
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

    private function resolveCategoryClassId($school, ?string $classId): ?string
    {
        $normalized = SkillScope::normalizeClassId($classId);

        if (! SkillScope::categorySeparatedByClass($school)) {
            return null;
        }

        if (! $normalized) {
            return null;
        }

        $exists = SchoolClass::query()
            ->where('school_id', $school->id)
            ->whereKey($normalized)
            ->exists();

        if (! $exists) {
            abort(422, 'Selected class was not found for this school.');
        }

        return $normalized;
    }

    private function assertUniqueCategoryName(
        string $schoolId,
        string $name,
        ?string $classId,
        ?string $ignoreId = null
    ): void {
        $query = SkillCategory::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)]);

        if ($classId) {
            $query->where('school_class_id', $classId);
        } else {
            $query->whereNull('school_class_id');
        }

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            abort(422, 'A skill category with this name already exists in the selected class scope.');
        }
    }

    private function transformCategory(SkillCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'school_class_id' => $category->school_class_id,
            'school_class' => $category->school_class
                ? [
                    'id' => $category->school_class->id,
                    'name' => $category->school_class->name,
                ]
                : null,
        ];
    }
}
