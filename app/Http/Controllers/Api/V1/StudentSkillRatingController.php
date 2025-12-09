<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\SkillRating;
use App\Models\SkillType;
use App\Models\Student;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentSkillRatingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/students/{student}/skill-ratings",
     *     tags={"school-v1.4"},
     *     summary="List skill ratings for a student",
     *     description="Returns the student's skill ratings filtered by session and term.",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         description="Session ID (defaults to student's current session)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="term_id",
     *         in="query",
     *         required=false,
     *         description="Term ID (defaults to student's current term)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="List returned successfully"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        $this->assertStudentAccess($request, $student);

        $sessionId = $request->input('session_id', $student->current_session_id);
        $termId = $request->input('term_id', $student->current_term_id);

        $ratings = $student->skill_ratings()
            ->when($sessionId, fn ($query) => $query->where('session_id', $sessionId))
            ->when($termId, fn ($query) => $query->where('term_id', $termId))
            ->with('skill_type:id,name,description')
            ->orderBy('skill_type_id')
            ->get()
            ->map(fn (SkillRating $rating) => $this->transformSkillRating($rating))
            ->values();

        return response()->json(['data' => $ratings]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/students/{student}/skill-types",
     *     tags={"school-v1.4"},
     *     summary="List available skill types for a student",
     *     description="Returns skill types scoped to the student's school.",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Skill types returned"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function types(Request $request, Student $student): JsonResponse
    {
        $this->assertStudentAccess($request, $student);

        $types = SkillType::query()
            ->where('school_id', $student->school_id)
            ->with('skill_category:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (SkillType $type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'description' => $type->description,
                    'skill_category_id' => $type->skill_category_id,
                    'category' => optional($type->skill_category)->name,
                ];
            })
            ->values();

        return response()->json(['data' => $types]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/students/{student}/skill-ratings",
     *     tags={"school-v1.4"},
     *     summary="Create a skill rating for a student",
     *     description="Records a new skill rating for the given session and term.",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"skill_type_id","rating_value"},
     *             @OA\Property(property="session_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="term_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="skill_type_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="rating_value", type="integer", example=4, minimum=1, maximum=5)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Skill rating created"),
     *     @OA\Response(response=409, description="Duplicate rating"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $this->assertStudentAccess($request, $student);

        $validated = $request->validate([
            'session_id' => ['nullable', 'uuid', Rule::exists('sessions', 'id')->where('school_id', $student->school_id)],
            'term_id' => ['nullable', 'uuid', Rule::exists('terms', 'id')->where('school_id', $student->school_id)],
            'skill_type_id' => ['required', 'uuid', Rule::exists('skill_types', 'id')->where('school_id', $student->school_id)],
            'rating_value' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $session = $this->resolveSession($student, $validated['session_id'] ?? null);
        $term = $this->resolveTerm($student, $validated['term_id'] ?? null);
        $skillType = $this->resolveSkillType($student, $validated['skill_type_id']);

        if (! $session || ! $term) {
            return response()->json([
                'message' => 'Session and term are required to record skill ratings.',
            ], 422);
        }

        if ($term->session_id !== $session->id) {
            return response()->json([
                'message' => 'The selected term does not belong to the supplied session.',
            ], 422);
        }

        if ($this->isTermClosed($term)) {
            return response()->json([
                'message' => 'Skill ratings can no longer be recorded for this term.',
            ], 422);
        }

        $alreadyExists = SkillRating::query()
            ->where('student_id', $student->id)
            ->where('session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('skill_type_id', $skillType->id)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'A rating for this skill has already been recorded for the selected term.',
            ], 409);
        }

        $rating = SkillRating::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'session_id' => $session->id,
            'term_id' => $term->id,
            'skill_type_id' => $skillType->id,
            'rating_value' => (int) $validated['rating_value'],
        ])->load('skill_type:id,name,description');

        return response()->json([
            'data' => $this->transformSkillRating($rating),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/students/{student}/skill-ratings/{skillRating}",
     *     tags={"school-v1.4"},
     *     summary="Update a student's skill rating",
     *     description="Updates session, term, skill type, or rating value for an existing record.",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="skillRating",
     *         in="path",
     *         required=true,
     *         description="Skill rating ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="session_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="term_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="skill_type_id", type="string", format="uuid", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     *             @OA\Property(property="rating_value", type="integer", example=5, minimum=1, maximum=5)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Skill rating updated"),
     *     @OA\Response(response=404, description="Skill rating not found for student"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Student $student, SkillRating $skillRating): JsonResponse
    {
        $this->assertStudentAccess($request, $student);

        if ($skillRating->student_id !== $student->id) {
            return response()->json(['message' => 'Skill rating not found for this student.'], 404);
        }

        $validated = $request->validate([
            'session_id' => ['sometimes', 'uuid', Rule::exists('sessions', 'id')->where('school_id', $student->school_id)],
            'term_id' => ['sometimes', 'uuid', Rule::exists('terms', 'id')->where('school_id', $student->school_id)],
            'skill_type_id' => ['sometimes', 'uuid', Rule::exists('skill_types', 'id')->where('school_id', $student->school_id)],
            'rating_value' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ]);

        $session = $this->resolveSession($student, $validated['session_id'] ?? $skillRating->session_id);
        $term = $this->resolveTerm($student, $validated['term_id'] ?? $skillRating->term_id);
        $skillType = $this->resolveSkillType($student, $validated['skill_type_id'] ?? $skillRating->skill_type_id);

        if (! $session || ! $term) {
            return response()->json([
                'message' => 'Session and term are required to update skill ratings.',
            ], 422);
        }

        if ($term->session_id !== $session->id) {
            return response()->json([
                'message' => 'The selected term does not belong to the supplied session.',
            ], 422);
        }

        if ($this->isTermClosed($term)) {
            return response()->json([
                'message' => 'Skill ratings can no longer be updated for this term.',
            ], 422);
        }

        $duplicateExists = SkillRating::query()
            ->where('student_id', $student->id)
            ->where('session_id', $session->id)
            ->where('term_id', $term->id)
            ->where('skill_type_id', $skillType->id)
            ->where('id', '!=', $skillRating->id)
            ->exists();

        if ($duplicateExists) {
            return response()->json([
                'message' => 'A rating for this skill already exists for the selected term.',
            ], 409);
        }

        if (array_key_exists('rating_value', $validated)) {
            $skillRating->rating_value = (int) $validated['rating_value'];
        }

        $skillRating->session_id = $session->id;
        $skillRating->term_id = $term->id;
        $skillRating->skill_type_id = $skillType->id;

        $skillRating->save();

        return response()->json([
            'data' => $this->transformSkillRating($skillRating->fresh()->load('skill_type:id,name,description')),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/students/{student}/skill-ratings/{skillRating}",
     *     tags={"school-v1.4"},
     *     summary="Delete a student's skill rating",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="skillRating",
     *         in="path",
     *         required=true,
     *         description="Skill rating ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=204, description="Skill rating deleted"),
     *     @OA\Response(response=404, description="Skill rating not found for student"),
     *     @OA\Response(response=422, description="Term closed for updates")
     * )
     */
    public function destroy(Request $request, Student $student, SkillRating $skillRating): JsonResponse
    {
        $this->assertStudentAccess($request, $student);

        if ($skillRating->student_id !== $student->id) {
            return response()->json(['message' => 'Skill rating not found for this student.'], 404);
        }

        $term = $skillRating->term()->first();
        if ($term && $this->isTermClosed($term)) {
            return response()->json([
                'message' => 'Skill ratings can no longer be removed for this term.',
            ], 422);
        }

        $skillRating->delete();

        return response()->json(null, 204);
    }

    private function assertStudentAccess(Request $request, Student $student): void
    {
        $user = $request->user();

        if (! $user || $user->school_id !== $student->school_id) {
            abort(403, 'You are not allowed to perform this action for the selected student.');
        }
    }

    private function resolveSession(Student $student, ?string $sessionId): ?Session
    {
        $id = $sessionId ?? $student->current_session_id;

        if (! $id) {
            return null;
        }

        return Session::query()
            ->where('school_id', $student->school_id)
            ->find($id);
    }

    private function resolveTerm(Student $student, ?string $termId): ?Term
    {
        $id = $termId ?? $student->current_term_id;

        if (! $id) {
            return null;
        }

        return Term::query()
            ->where('school_id', $student->school_id)
            ->find($id);
    }

    private function resolveSkillType(Student $student, string $skillTypeId): SkillType
    {
        return SkillType::query()
            ->where('school_id', $student->school_id)
            ->findOrFail($skillTypeId);
    }

    private function isTermClosed(Term $term): bool
    {
        $status = strtolower((string) $term->status);
        $lockedStatuses = collect(config('school.skill_rating_lock_statuses', ['archived']))
            ->map(fn ($value) => strtolower((string) $value))
            ->filter()
            ->values()
            ->all();

        if (in_array($status, $lockedStatuses, true)) {
            return true;
        }

        if (! $term->end_date) {
            return false;
        }

        $graceDaysConfig = config('school.skill_rating_grace_days', -1);
        $graceDays = is_numeric($graceDaysConfig) ? (int) $graceDaysConfig : -1;

        if ($graceDays < 0) {
            return false;
        }

        $cutoff = $term->end_date->copy()->addDays($graceDays)->endOfDay();

        return Carbon::now()->greaterThan($cutoff);
    }

    private function transformSkillRating(SkillRating $skillRating): array
    {
        $skillType = $skillRating->skill_type;

        return [
            'id' => $skillRating->id,
            'student_id' => $skillRating->student_id,
            'session_id' => $skillRating->session_id,
            'term_id' => $skillRating->term_id,
            'skill_type_id' => $skillRating->skill_type_id,
            'skill_type' => $skillType ? [
                'id' => $skillType->id,
                'name' => $skillType->name,
                'description' => $skillType->description,
            ] : null,
            'rating_value' => $skillRating->rating_value,
            'created_at' => optional($skillRating->created_at)->toISOString(),
            'updated_at' => optional($skillRating->updated_at)->toISOString(),
        ];
    }
}
