<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SkillCategory;
use App\Models\SkillType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        $types = SkillType::query()
            ->where('school_id', $school->id)
            ->when($request->filled('skill_category_id'), function ($query) use ($request) {
                $query->where('skill_category_id', $request->input('skill_category_id'));
            })
            ->with('skill_category:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (SkillType $type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'description' => $type->description,
                    'weight' => $type->weight,
                    'skill_category_id' => $type->skill_category_id,
                    'category' => optional($type->skill_category)->name,
                ];
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
            'skill_category_id' => ['required', 'uuid', Rule::exists('skill_categories', 'id')->where('school_id', $school->id)],
            'name' => ['required', 'string', 'max:500', 'regex:/.*\S.*/', Rule::unique('skill_types', 'name')->where('school_id', $school->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'between:0,999.99'],
        ]);

        $name = trim($validated['name']);

        $type = SkillType::create([
            'id' => (string) Str::uuid(),
            'skill_category_id' => $validated['skill_category_id'],
            'school_id' => $school->id,
            'name' => $name,
            'description' => $validated['description'] ?? null,
            'weight' => $validated['weight'] ?? null,
        ])->load('skill_category:id,name');

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
            'skill_category_id' => ['required', 'uuid', Rule::exists('skill_categories', 'id')->where('school_id', $school->id)],
            'names' => ['required', 'array', 'min:1'],
            'names.*' => ['required', 'string', 'max:500', 'regex:/.*\S.*/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'between:0,999.99'],
        ]);

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

        $existingNames = SkillType::query()
            ->where('school_id', $school->id)
            ->whereIn('name', $names)
            ->pluck('name')
            ->values();

        if ($existingNames->isNotEmpty()) {
            return response()->json([
                'message' => 'Some skill names already exist for this school.',
                'errors' => [
                    'names' => ['These skill names already exist: '.$existingNames->implode(', ')],
                ],
            ], 422);
        }

        $description = $validated['description'] ?? null;
        $weight = $validated['weight'] ?? null;
        $categoryId = $validated['skill_category_id'];

        $created = DB::transaction(function () use ($names, $description, $weight, $categoryId, $school) {
            return $names->map(function (string $name) use ($description, $weight, $categoryId, $school) {
                return SkillType::create([
                    'id' => (string) Str::uuid(),
                    'skill_category_id' => $categoryId,
                    'school_id' => $school->id,
                    'name' => $name,
                    'description' => $description,
                    'weight' => $weight,
                ])->load('skill_category:id,name');
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
            'skill_category_id' => ['sometimes', 'required', 'uuid', Rule::exists('skill_categories', 'id')->where('school_id', $skillType->school_id)],
            'name' => ['sometimes', 'required', 'string', 'max:500', 'regex:/.*\S.*/', Rule::unique('skill_types', 'name')->ignore($skillType->id)->where('school_id', $skillType->school_id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'between:0,999.99'],
        ]);

        if (array_key_exists('skill_category_id', $validated)) {
            $skillType->skill_category_id = $validated['skill_category_id'];
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

        if ($skillType->isDirty()) {
            $skillType->save();
        }

        return response()->json([
            'message' => 'Skill updated successfully.',
            'data' => $this->transformType($skillType->fresh('skill_category:id,name')),
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
        ];
    }
}
