<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SkillCategory;
use App\Models\SchoolClass;
use App\Models\SkillType;
use App\Support\SkillScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="school-v1.9",
 *     description="v1.9 – Results, Components, Grading & Skills"
 * )
 */
class SkillTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/settings/skill-types",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="List skill types",
     *     description="Returns skill types for the authenticated school. Supports filtering by skill_category_id.",
     *     @OA\Parameter(
     *         name="skill_category_id",
     *         in="query",
     *         required=false,
     *         description="Filter by skill category",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="List returned"),
     *     @OA\Response(response=422, description="User not linked to a school")
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

        $types = SkillScope::applyTypeVisibility(
                SkillType::query(),
                $school,
                $classId
            )
            ->when($request->filled('skill_category_id'), function ($query) use ($request) {
                $query->where('skill_category_id', $request->input('skill_category_id'));
            })
            ->with([
                'skill_category:id,name,school_class_id',
                'school_class:id,name',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (SkillType $type) {
                return $this->transformType($type);
            })
            ->values();

        return response()->json(['data' => $types]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/settings/skill-types",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="Create a skill type",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"skill_category_id","name"},
     *             @OA\Property(property="skill_category_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="name", type="string", example="Teamwork"),
     *             @OA\Property(property="description", type="string", example="Ability to collaborate effectively"),
     *             @OA\Property(property="weight", type="number", format="float", example=10.5)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Skill type created"),
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
            'skill_category_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:500', 'regex:/.*\S.*/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'between:0,999.99'],
            'school_class_id' => ['nullable', 'uuid'],
        ]);

        $category = $this->resolveCategory($school->id, $validated['skill_category_id']);
        $name = trim($validated['name']);
        $classId = $this->resolveTypeClassId($school, $validated['school_class_id'] ?? null);

        $this->assertCategoryMatchesScope($school, $category, $classId);
        $this->assertUniqueTypeName($school, $name, $classId);

        $type = SkillType::create([
            'id' => (string) Str::uuid(),
            'skill_category_id' => $category->id,
            'school_id' => $school->id,
            'school_class_id' => $classId,
            'name' => $name,
            'description' => $validated['description'] ?? null,
            'weight' => $validated['weight'] ?? null,
        ])->load(['skill_category:id,name,school_class_id', 'school_class:id,name']);

        return response()->json([
            'message' => 'Skill created successfully.',
            'data' => $this->transformType($type),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/settings/skill-types/bulk",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="Create multiple skill types",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"skill_category_id","names"},
     *             @OA\Property(property="skill_category_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(
     *                 property="names",
     *                 type="array",
     *                 @OA\Items(type="string", example="Teamwork")
     *             ),
     *             @OA\Property(property="description", type="string", example="Optional shared description for all skills"),
     *             @OA\Property(property="weight", type="number", format="float", example=10.5)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Skill types created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulkStore(Request $request)
    {
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'skill_category_id' => ['required', 'uuid'],
            'names' => ['required', 'array', 'min:1'],
            'names.*' => ['required', 'string', 'max:500', 'regex:/.*\S.*/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'between:0,999.99'],
            'school_class_id' => ['nullable', 'uuid'],
        ]);

        $category = $this->resolveCategory($school->id, $validated['skill_category_id']);
        $names = collect($validated['names'])
            ->map(fn (string $name) => trim($name))
            ->filter(fn (string $name) => $name !== '')
            ->values();

        $lowered = $names->map(fn (string $name) => Str::lower($name));
        if ($lowered->unique()->count() !== $lowered->count()) {
            return response()->json([
                'message' => 'Duplicate skill names were provided in the request.',
                'errors' => [
                    'names' => ['Skill names must be unique in a single request.'],
                ],
            ], 422);
        }

        $description = $validated['description'] ?? null;
        $weight = $validated['weight'] ?? null;
        $classId = $this->resolveTypeClassId($school, $validated['school_class_id'] ?? null);

        $this->assertCategoryMatchesScope($school, $category, $classId);

        $existingNames = $names
            ->filter(fn (string $name) => $this->typeNameExists($school, $name, $classId))
            ->values();

        if ($existingNames->isNotEmpty()) {
            return response()->json([
                'message' => 'Some skill names already exist for the selected class scope.',
                'errors' => [
                    'names' => ['These skill names already exist: '.$existingNames->implode(', ')],
                ],
            ], 422);
        }

        $created = DB::transaction(function () use ($names, $description, $weight, $category, $classId, $school) {
            return $names->map(function (string $name) use ($description, $weight, $category, $classId, $school) {
                return SkillType::create([
                    'id' => (string) Str::uuid(),
                    'skill_category_id' => $category->id,
                    'school_id' => $school->id,
                    'school_class_id' => $classId,
                    'name' => $name,
                    'description' => $description,
                    'weight' => $weight,
                ])->load(['skill_category:id,name,school_class_id', 'school_class:id,name']);
            });
        });

        return response()->json([
            'message' => sprintf(
                '%d skill%s created successfully.',
                $created->count(),
                $created->count() === 1 ? '' : 's'
            ),
            'data' => $created->map(fn (SkillType $type) => $this->transformType($type))->values(),
        ], 201);
    }

    public function bulkUpdateScope(Request $request)
    {
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'skill_type_ids' => ['required', 'array', 'min:1'],
            'skill_type_ids.*' => ['required', 'uuid'],
            'school_class_id' => ['nullable', 'uuid'],
        ]);

        $classId = $this->resolveTypeClassId($school, $validated['school_class_id'] ?? null);
        $skillTypeIds = collect($validated['skill_type_ids'])
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $types = SkillType::query()
            ->where('school_id', $school->id)
            ->whereIn('id', $skillTypeIds)
            ->with(['skill_category:id,name,school_class_id', 'school_class:id,name'])
            ->get();

        if ($types->count() !== $skillTypeIds->count()) {
            return response()->json([
                'message' => 'One or more selected skills were not found for this school.',
            ], 404);
        }

        foreach ($types as $type) {
            $this->assertCategoryMatchesScope($school, $type->skill_category, $classId);
            $this->assertUniqueTypeName($school, $type->name, $classId, $type->id);
        }

        DB::transaction(function () use ($types, $classId) {
            foreach ($types as $type) {
                $type->school_class_id = $classId;
                if ($type->isDirty()) {
                    $type->save();
                }
            }
        });

        $updated = SkillType::query()
            ->whereIn('id', $skillTypeIds)
            ->with(['skill_category:id,name,school_class_id', 'school_class:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (SkillType $type) => $this->transformType($type))
            ->values();

        return response()->json([
            'message' => sprintf(
                '%d skill%s assigned successfully.',
                $updated->count(),
                $updated->count() === 1 ? '' : 's'
            ),
            'data' => $updated,
        ]);
    }

    public function bulkCopyToScope(Request $request)
    {
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'skill_type_ids' => ['required', 'array', 'min:1'],
            'skill_type_ids.*' => ['required', 'uuid'],
            'school_class_id' => ['nullable', 'uuid'],
        ]);

        $targetClassId = $this->resolveTypeClassId($school, $validated['school_class_id'] ?? null);
        $skillTypeIds = collect($validated['skill_type_ids'])
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $types = SkillType::query()
            ->where('school_id', $school->id)
            ->whereIn('id', $skillTypeIds)
            ->with([
                'skill_category:id,name,description,school_class_id',
                'school_class:id,name',
            ])
            ->get();

        if ($types->count() !== $skillTypeIds->count()) {
            return response()->json([
                'message' => 'One or more selected skills were not found for this school.',
            ], 404);
        }

        $copied = collect();
        $skipped = collect();

        DB::transaction(function () use ($types, $targetClassId, $school, $copied, $skipped) {
            foreach ($types as $type) {
                $targetCategory = $this->resolveTargetCategoryForCopy(
                    $school,
                    $type->skill_category,
                    $targetClassId
                );

                if ($this->typeNameExists($school, $type->name, $targetClassId)) {
                    $skipped->push([
                        'id' => $type->id,
                        'name' => $type->name,
                        'reason' => 'A skill with this name already exists in the target class scope.',
                    ]);
                    continue;
                }

                $created = SkillType::create([
                    'id' => (string) Str::uuid(),
                    'skill_category_id' => $targetCategory->id,
                    'school_id' => $school->id,
                    'school_class_id' => $targetClassId,
                    'name' => $type->name,
                    'description' => $type->description,
                    'weight' => $type->weight,
                ])->load(['skill_category:id,name,school_class_id', 'school_class:id,name']);

                $copied->push($created);
            }
        });

        $copiedData = $copied
            ->map(fn (SkillType $type) => $this->transformType($type))
            ->values();

        $message = sprintf(
            '%d skill%s copied successfully.',
            $copiedData->count(),
            $copiedData->count() === 1 ? '' : 's'
        );

        if ($skipped->isNotEmpty()) {
            $message .= sprintf(
                ' %d existing skill%s skipped.',
                $skipped->count(),
                $skipped->count() === 1 ? '' : 's'
            );
        }

        return response()->json([
            'message' => $message,
            'data' => $copiedData,
            'skipped' => $skipped->values(),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/settings/skill-types/{skillType}",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="Update a skill type",
     *     @OA\Parameter(
     *         name="skillType",
     *         in="path",
     *         required=true,
     *         description="Skill type ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="skill_category_id", type="string", format="uuid"),
     *             @OA\Property(property="name", type="string", example="Creativity"),
     *             @OA\Property(property="description", type="string", example="Problem solving and originality"),
     *             @OA\Property(property="weight", type="number", format="float", example=5.0)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Skill type updated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, SkillType $skillType)
    {
        $this->authorizeType($request, $skillType);

        $validated = $request->validate([
            'skill_category_id' => ['sometimes', 'required', 'uuid'],
            'name' => ['sometimes', 'required', 'string', 'max:500', 'regex:/.*\S.*/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'between:0,999.99'],
            'school_class_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $school = $request->user()->school;

        $category = $skillType->skill_category;

        if (array_key_exists('skill_category_id', $validated)) {
            $category = $this->resolveCategory($skillType->school_id, $validated['skill_category_id']);
            $skillType->skill_category_id = $category->id;
        }

        if (array_key_exists('school_class_id', $validated)) {
            $skillType->school_class_id = $this->resolveTypeClassId($school, $validated['school_class_id']);
        }

        if (array_key_exists('name', $validated)) {
            $skillType->name = trim($validated['name']);
        }

        if (array_key_exists('description', $validated)) {
            $skillType->description = $validated['description'] ?? null;
        }

        if (array_key_exists('weight', $validated)) {
            $skillType->weight = $validated['weight'] ?? null;
        }

        $this->assertCategoryMatchesScope($school, $category, $skillType->school_class_id);
        $this->assertUniqueTypeName($school, $skillType->name, $skillType->school_class_id, $skillType->id);

        if ($skillType->isDirty()) {
            $skillType->save();
        }

        return response()->json([
            'message' => 'Skill updated successfully.',
            'data' => $this->transformType($skillType->fresh(['skill_category:id,name,school_class_id', 'school_class:id,name'])),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/settings/skill-types/{skillType}",
     *     tags={"school-v1.4","school-v1.9"},
     *     summary="Delete a skill type",
     *     @OA\Parameter(
     *         name="skillType",
     *         in="path",
     *         required=true,
     *         description="Skill type ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Skill type deleted"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(Request $request, SkillType $skillType)
    {
        $this->authorizeType($request, $skillType);

        $skillType->delete();

        return response()->json([
            'message' => 'Skill deleted successfully.',
        ]);
    }

    private function authorizeType(Request $request, SkillType $skillType): void
    {
        $user = $request->user();

        if (! $user || $user->school_id !== $skillType->school_id) {
            abort(403, 'You are not allowed to manage this skill.');
        }
    }

    private function transformType(SkillType $skillType): array
    {
        return [
            'id' => $skillType->id,
            'name' => $skillType->name,
            'description' => $skillType->description,
            'weight' => $skillType->weight,
            'skill_category_id' => $skillType->skill_category_id,
            'category' => optional($skillType->skill_category)->name,
            'category_school_class_id' => optional($skillType->skill_category)->school_class_id,
            'school_class_id' => $skillType->school_class_id,
            'school_class' => $skillType->school_class
                ? [
                    'id' => $skillType->school_class->id,
                    'name' => $skillType->school_class->name,
                ]
                : null,
        ];
    }

    private function resolveCategory(string $schoolId, string $categoryId): SkillCategory
    {
        return SkillCategory::query()
            ->where('school_id', $schoolId)
            ->findOrFail($categoryId);
    }

    private function resolveTypeClassId($school, ?string $classId): ?string
    {
        $normalized = SkillScope::normalizeClassId($classId);

        if (! SkillScope::typeSeparatedByClass($school)) {
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

    private function resolveTargetCategoryForCopy($school, SkillCategory $category, ?string $targetClassId): SkillCategory
    {
        if (
            ! SkillScope::categorySeparatedByClass($school)
            || $category->school_class_id === null
            || $category->school_class_id === $targetClassId
        ) {
            return $category;
        }

        $existing = SkillCategory::query()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($category->name)])
            ->when(
                $targetClassId,
                fn ($query) => $query->where('school_class_id', $targetClassId),
                fn ($query) => $query->whereNull('school_class_id')
            )
            ->first();

        if ($existing) {
            return $existing;
        }

        return SkillCategory::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'school_class_id' => $targetClassId,
            'name' => $category->name,
            'description' => $category->description,
        ]);
    }

    private function assertCategoryMatchesScope($school, SkillCategory $category, ?string $typeClassId): void
    {
        if (! SkillScope::categorySeparatedByClass($school)) {
            return;
        }

        if ($category->school_class_id !== null && $typeClassId !== null && $category->school_class_id !== $typeClassId) {
            abort(422, 'The selected category belongs to a different class scope.');
        }
    }

    private function assertUniqueTypeName($school, string $name, ?string $classId, ?string $ignoreId = null): void
    {
        if ($this->typeNameExists($school, $name, $classId, $ignoreId)) {
            abort(422, 'A skill with this name already exists in the selected class scope.');
        }
    }

    private function typeNameExists($school, string $name, ?string $classId, ?string $ignoreId = null): bool
    {
        $query = SkillType::query()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)]);

        if (SkillScope::typeSeparatedByClass($school)) {
            if ($classId) {
                $query->where('school_class_id', $classId);
            } else {
                $query->whereNull('school_class_id');
            }
        }

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
