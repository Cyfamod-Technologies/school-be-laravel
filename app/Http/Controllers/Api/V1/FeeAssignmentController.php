<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeeAssignmentResource;
use App\Models\FeeStructure;
use App\Services\Fees\BillGenerationService;
use App\Services\Fees\FeeAssignmentService;
use Illuminate\Http\Request;

/**
 * Scope-aware fee assignment: a fee billed to the whole school, one class, one
 * class arm, or a hand-picked set of students.
 *
 * The older /fees/structures endpoints stay for the class-only screen that
 * predates this; they operate on the same table filtered to class scope.
 *
 * @OA\Tag(
 *     name="school-v2.5",
 *     description="Fees - assignments and bills"
 * )
 */
class FeeAssignmentController extends Controller
{
    public function __construct(
        private readonly FeeAssignmentService $assignments,
        private readonly BillGenerationService $bills,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/fees/assignments",
     *     tags={"school-v2.5"},
     *     summary="List fee assignments across every scope",
     *
     *     @OA\Response(response=200, description="Assignments returned")
     * )
     */
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'finance.assignments.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof \App\Models\School) {
            return $school;
        }

        $perPage = max((int) $request->input('per_page', 15), 1);

        $assignments = FeeStructure::query()
            ->where('school_id', $school->id)
            ->with(['feeItem', 'schoolClass', 'classArm', 'session', 'term', 'creator'])
            ->withCount('students')
            ->when($request->filled('scope'), fn ($q) => $q->where('scope', $request->input('scope')))
            ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->input('session_id')))
            ->when($request->filled('term_id'), fn ($q) => $q->where('term_id', $request->input('term_id')))
            ->when($request->filled('school_class_id'), fn ($q) => $q->where('school_class_id', $request->input('school_class_id')))
            ->when($request->filled('class_arm_id'), fn ($q) => $q->where('class_arm_id', $request->input('class_arm_id')))
            ->when($request->filled('fee_item_id'), fn ($q) => $q->where('fee_item_id', $request->input('fee_item_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where(
                'is_active',
                filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)
            ))
            ->when($request->filled('search'), fn ($q) => $q->whereHas(
                'feeItem',
                fn ($item) => $item->where('name', 'like', '%'.$request->input('search').'%')
            ))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return FeeAssignmentResource::collection($assignments);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/fees/assignments",
     *     tags={"school-v2.5"},
     *     summary="Assign a fee to a school, class, class arm or set of students",
     *
     *     @OA\Response(response=201, description="Assigned"),
     *     @OA\Response(response=422, description="Validation error or duplicate")
     * )
     */
    public function store(Request $request)
    {
        $this->ensurePermission($request, 'finance.assignments.create');

        $school = $this->requireSchool($request);
        if (! $school instanceof \App\Models\School) {
            return $school;
        }

        $validated = $this->validatePayload($request);

        $assignment = $this->assignments->create($school, $validated, $request->user());

        // Bills exist to be looked at, so they are brought up to date with the
        // assignment straight away rather than waiting for a nightly run.
        $summary = $this->bills->syncAssignment($assignment, $request->user());

        return (new FeeAssignmentResource($this->loadRelations($assignment)))
            ->additional(['meta' => ['bill_sync' => $summary]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/assignments/{feeAssignment}",
     *     tags={"school-v2.5"},
     *     summary="Get one fee assignment",
     *
     *     @OA\Response(response=200, description="Assignment returned"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, FeeStructure $feeAssignment)
    {
        $this->ensurePermission($request, 'finance.assignments.view');

        if ($feeAssignment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return new FeeAssignmentResource($this->loadRelations($feeAssignment));
    }

    /**
     * @OA\Put(
     *     path="/api/v1/fees/assignments/{feeAssignment}",
     *     tags={"school-v2.5"},
     *     summary="Update a fee assignment",
     *
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, FeeStructure $feeAssignment)
    {
        $this->ensurePermission($request, 'finance.assignments.update');

        if ($feeAssignment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'description' => 'sometimes|nullable|string|max:255',
            'due_date' => 'sometimes|nullable|date',
            'is_mandatory' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'student_ids' => 'sometimes|array|min:1',
            'student_ids.*' => 'uuid',
        ]);

        // Students dropped from the assignment stop being billed by it, so the
        // set before the edit has to be re-synced too.
        $previousStudentIds = $this->assignments->resolveStudentIds($feeAssignment);

        $this->assignments->update($feeAssignment, $validated, $request->user());

        $summary = $this->syncStudents(
            $feeAssignment,
            array_unique(array_merge($previousStudentIds, $this->assignments->resolveStudentIds($feeAssignment))),
            $request,
        );

        return (new FeeAssignmentResource($this->loadRelations($feeAssignment->fresh())))
            ->additional(['meta' => ['bill_sync' => $summary]]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/fees/assignments/{feeAssignment}",
     *     tags={"school-v2.5"},
     *     summary="Remove a fee assignment",
     *
     *     @OA\Response(response=200, description="Removed"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, FeeStructure $feeAssignment)
    {
        $this->ensurePermission($request, 'finance.assignments.delete');

        if ($feeAssignment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // The service takes the fee off every bill it raised as part of the
        // delete -- it has to, because the nullOnDelete foreign key would
        // otherwise leave those lines unattributable and still payable.
        $removed = $this->assignments->delete($feeAssignment, $request->user());

        return response()->json([
            'message' => 'Fee assignment removed.',
            'meta' => ['bill_items_removed' => $removed],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/fees/assignments/preview",
     *     tags={"school-v2.5"},
     *     summary="Count the students an assignment would reach, and what it totals",
     *
     *     @OA\Response(response=200, description="Preview returned")
     * )
     */
    public function preview(Request $request)
    {
        $this->ensurePermission($request, 'finance.assignments.view');

        $school = $this->requireSchool($request);
        if (! $school instanceof \App\Models\School) {
            return $school;
        }

        return response()->json([
            'data' => $this->assignments->preview($school, $this->validatePayload($request)),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/fees/assignments/{feeAssignment}/students",
     *     tags={"school-v2.5"},
     *     summary="Replace the students a student-scoped assignment bills",
     *
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=422, description="Not a student-scoped assignment")
     * )
     */
    public function syncAssignmentStudents(Request $request, FeeStructure $feeAssignment)
    {
        $this->ensurePermission($request, 'finance.assignments.update');

        if ($feeAssignment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if ($feeAssignment->scope !== FeeStructure::SCOPE_STUDENT) {
            return response()->json([
                'message' => 'Only student-specific assignments carry a student list.',
            ], 422);
        }

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'uuid',
        ]);

        $previousStudentIds = $this->assignments->resolveStudentIds($feeAssignment);

        $this->assignments->update($feeAssignment, $validated, $request->user());

        $summary = $this->syncStudents(
            $feeAssignment,
            array_unique(array_merge($previousStudentIds, $validated['student_ids'])),
            $request,
        );

        return (new FeeAssignmentResource($this->loadRelations($feeAssignment->fresh())))
            ->additional(['meta' => ['bill_sync' => $summary]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'scope' => ['required', 'string', 'in:'.implode(',', FeeStructure::SCOPES)],
            'session_id' => 'required|uuid',
            'term_id' => 'required|uuid',
            'fee_item_id' => 'required|uuid',
            'amount' => 'required|numeric|min:0',
            'school_class_id' => 'nullable|uuid',
            'class_arm_id' => 'nullable|uuid',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'uuid',
            'description' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ]);
    }

    /**
     * @param  array<int, string>  $studentIds
     * @return array<string, int>|null
     */
    private function syncStudents(FeeStructure $assignment, array $studentIds, Request $request): ?array
    {
        if ($studentIds === []) {
            return null;
        }

        return $this->bills->generateForSchool(
            $assignment->school_id,
            $assignment->session_id,
            $assignment->term_id,
            array_values($studentIds),
            $request->user(),
        );
    }

    private function loadRelations(FeeStructure $assignment): FeeStructure
    {
        return $assignment->load(['feeItem', 'schoolClass', 'classArm', 'session', 'term', 'students', 'creator']);
    }

    /**
     * @return \App\Models\School|\Illuminate\Http\JsonResponse
     */
    private function requireSchool(Request $request)
    {
        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        return $school;
    }
}
