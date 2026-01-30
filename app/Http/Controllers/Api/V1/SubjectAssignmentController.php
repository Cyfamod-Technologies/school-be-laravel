<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\ClassSection;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="school-v1.7",
 *     description="v1.7 – Subject & Teacher Assignments"
 * )
 */
class SubjectAssignmentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/settings/subject-assignments",
     *     tags={"school-v1.7"},
     *     summary="List subject-to-class assignments",
     *     description="Paginated list filtered by subject, class, arm, section, or search.",
     *     @OA\Parameter(name="subject_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="school_class_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="class_arm_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="class_section_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="search", in="query", required=false, description="Search subject name/code", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="Assignments returned"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $this->ensurePermission($request, ['subject.assignments.view', 'subject.assignments.class']);
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $perPage = max((int) $request->input('per_page', 15), 1);
        $sortBy = $request->input('sortBy', 'created_at');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'updated_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $query = SubjectAssignment::query()
            ->with([
                'subject:id,name,code,school_id',
                'school_class:id,name,slug,school_id',
                'class_arm:id,name,school_class_id',
                'class_section:id,name,class_arm_id',
            ])
            ->whereHas('subject', fn (Builder $builder) => $builder->where('school_id', $school->id));

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }

        if ($request->filled('school_class_id')) {
            $query->where('school_class_id', $request->input('school_class_id'));
        }

        if ($request->filled('class_arm_id')) {
            $query->where('class_arm_id', $request->input('class_arm_id'));
        }

        if ($request->filled('class_section_id')) {
            $query->where('class_section_id', $request->input('class_section_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('subject', function (Builder $builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $assignments = $query->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($assignments);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/settings/subject-assignments",
     *     tags={"school-v1.7"},
     *     summary="Create subject-to-class assignment",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"subject_id","school_class_id","class_arm_id"},
     *             @OA\Property(property="subject_id", type="string", format="uuid"),
     *             @OA\Property(property="school_class_id", type="string", format="uuid"),
     *             @OA\Property(property="class_arm_id", type="string", format="uuid"),
     *             @OA\Property(property="class_section_id", type="string", format="uuid", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Assignment created"),
     *     @OA\Response(response=422, description="Validation error or duplicate assignment")
     * )
     */
    public function store(Request $request)
    {
        $this->ensurePermission($request, ['subject.assignments.create', 'subject.assignments.class']);
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'subject_id' => ['required', 'uuid'],
            'school_class_id' => ['required', 'uuid'],
            'class_arm_id' => ['required', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
        ]);

        [$subject, $class, $classArm, $classSection] = $this->resolveAssignmentEntities(
            $school->id,
            $validated['subject_id'],
            $validated['school_class_id'],
            $validated['class_arm_id'],
            $validated['class_section_id'] ?? null
        );

        if ($this->assignmentExists($subject->id, $class->id, $classArm->id, optional($classSection)->id)) {
            return response()->json([
                'message' => 'Subject is already assigned to the selected class context.',
            ], 422);
        }

        $assignment = SubjectAssignment::create([
            'id' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'class_arm_id' => $classArm->id,
            'class_section_id' => optional($classSection)->id,
        ]);

        return response()->json([
            'message' => 'Subject assigned successfully.',
            'data' => $assignment->load([
                'subject:id,name,code',
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
            ]),
        ], 201);
    }
    public function show(Request $request, SubjectAssignment $assignment)
    {
        $this->authorizeAssignment($request, $assignment);

        return response()->json([
            'id' => $assignment->id,
            'subject_id' => $assignment->subject_id,
            'school_class_id' => $assignment->school_class_id,
            'class_arm_id' => $assignment->class_arm_id,
            'class_section_id' => $assignment->class_section_id,
            'subject' => optional($assignment->subject)->only(['id', 'name', 'code']),
            'school_class' => optional($assignment->school_class)->only(['id', 'name']),
            'class_arm' => optional($assignment->class_arm)->only(['id', 'name']),
            'class_section' => optional($assignment->class_section)->only(['id', 'name']),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/settings/subject-assignments/{id}",
     *     tags={"school-v1.7"},
     *     summary="Update subject-to-class assignment",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Assignment ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="subject_id", type="string", format="uuid"),
     *             @OA\Property(property="school_class_id", type="string", format="uuid"),
     *             @OA\Property(property="class_arm_id", type="string", format="uuid"),
     *             @OA\Property(property="class_section_id", type="string", format="uuid", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Assignment updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error or duplicate assignment")
     * )
     */
    public function update(Request $request, SubjectAssignment $assignment)
    {
        $this->ensurePermission($request, ['subject.assignments.update', 'subject.assignments.class']);
        $this->authorizeAssignment($request, $assignment);

        $validated = $request->validate([
            'subject_id' => ['sometimes', 'required', 'uuid'],
            'school_class_id' => ['sometimes', 'required', 'uuid'],
            'class_arm_id' => ['sometimes', 'required', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
        ]);

        $subjectId = $validated['subject_id'] ?? $assignment->subject_id;
        $classId = $validated['school_class_id'] ?? $assignment->school_class_id;
        $classArmId = $validated['class_arm_id'] ?? $assignment->class_arm_id;
        $classSectionId = array_key_exists('class_section_id', $validated)
            ? $validated['class_section_id']
            : $assignment->class_section_id;

        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        [$subject, $class, $classArm, $classSection] = $this->resolveAssignmentEntities(
            $school->id,
            $subjectId,
            $classId,
            $classArmId,
            $classSectionId
        );

        if ($this->assignmentExists($subject->id, $class->id, $classArm->id, optional($classSection)->id, $assignment->id)) {
            return response()->json([
                'message' => 'Subject is already assigned to the selected class context.',
            ], 422);
        }

        $assignment->fill([
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'class_arm_id' => $classArm->id,
            'class_section_id' => optional($classSection)->id,
        ]);

        if ($assignment->isDirty()) {
            $assignment->save();
        }

        return response()->json([
            'message' => 'Subject assignment updated successfully.',
            'data' => $assignment->fresh()->load([
                'subject:id,name,code',
                'school_class:id,name',
                'class_arm:id,name',
                'class_section:id,name',
            ]),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/settings/subject-assignments/{id}",
     *     tags={"school-v1.7"},
     *     summary="Delete subject-to-class assignment",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Assignment ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Assignment deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, SubjectAssignment $assignment)
    {
        $this->ensurePermission($request, ['subject.assignments.delete', 'subject.assignments.class']);
        $this->authorizeAssignment($request, $assignment);
        $assignment->delete();

        return response()->json([
            'message' => 'Subject assignment removed successfully.',
        ]);
    }

    private function resolveAssignmentEntities(
        string $schoolId,
        string $subjectId,
        string $classId,
        string $classArmId,
        ?string $classSectionId
    ): array {
        $subject = Subject::where('id', $subjectId)
            ->where('school_id', $schoolId)
            ->first();

        if (! $subject) {
            abort(404, 'Subject not found for the authenticated school.');
        }

        $class = SchoolClass::where('id', $classId)
            ->where('school_id', $schoolId)
            ->first();

        if (! $class) {
            abort(404, 'Class not found for the authenticated school.');
        }

        $classArm = ClassArm::where('id', $classArmId)
            ->where('school_class_id', $class->id)
            ->first();

        if (! $classArm) {
            abort(404, 'Class arm not found or does not belong to the selected class.');
        }

        $classSection = null;
        if ($classSectionId) {
            $classSection = ClassSection::where('id', $classSectionId)
                ->where('class_arm_id', $classArm->id)
                ->first();

            if (! $classSection) {
                abort(404, 'Class section not found or does not belong to the selected class arm.');
            }
        }

        return [$subject, $class, $classArm, $classSection];
    }

    private function assignmentExists(string $subjectId, string $classId, string $classArmId, ?string $classSectionId, ?string $ignoreId = null): bool
    {
        $query = SubjectAssignment::query()
            ->where('subject_id', $subjectId)
            ->where('school_class_id', $classId)
            ->where('class_arm_id', $classArmId)
            ->when($classSectionId, fn (Builder $builder) => $builder->where('class_section_id', $classSectionId))
            ->when(! $classSectionId, fn (Builder $builder) => $builder->whereNull('class_section_id'));

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function authorizeAssignment(Request $request, SubjectAssignment $assignment): void
    {
        $schoolId = optional($request->user()->school)->id;

        $assignment->loadMissing('subject', 'school_class');

        $belongsToSchool = $schoolId && (
            optional($assignment->subject)->school_id === $schoolId
            || optional($assignment->school_class)->school_id === $schoolId
        );

        abort_unless($belongsToSchool, 404);
    }
}
