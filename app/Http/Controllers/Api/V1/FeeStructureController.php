<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="school-v2.4",
 *     description="Fee Management"
 * )
 * @OA\Tag(
 *     name="school-v2.0",
 *     description="v2.0 – Rollover, Promotions, Attendance, Fees, Roles"
 * )
 */
class FeeStructureController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/fees/structures",
     *     tags={"school-v2.4","school-v2.0"},
     *     summary="List fee structures",
     *
     *     @OA\Response(response=200, description="Fee structures returned")
     * )
     */
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'finance.fee-structures.view');

        $perPage = max((int) $request->input('per_page', 10), 1);

        $feeStructures = $request->user()->school->feeStructures()
            // These endpoints predate the scope-aware assignment model and
            // keep their original class-only contract. School-wide, class-arm
            // and student fees are managed through /fees/assignments.
            ->where('scope', FeeStructure::SCOPE_CLASS)
            ->with(['class', 'session', 'term', 'feeItem'])
            ->when($request->filled('class_id'), function ($query) use ($request) {
                $query->where('school_class_id', $request->class_id);
            })
            ->when($request->filled('session_id'), function ($query) use ($request) {
                $query->where('session_id', $request->session_id);
            })
            ->when($request->filled('term_id'), function ($query) use ($request) {
                $query->where('term_id', $request->term_id);
            })
            ->when($request->filled('fee_item_id'), function ($query) use ($request) {
                $query->where('fee_item_id', $request->fee_item_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($feeStructures);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/fees/structures",
     *     tags={"school-v2.4","school-v2.0"},
     *     summary="Create fee structure",
     *
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=422, description="Validation error or duplicate")
     * )
     */
    public function store(Request $request)
    {
        $this->ensurePermission($request, 'finance.fee-structures.create');

        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'class_id' => 'required|uuid|exists:classes,id',
            'session_id' => 'required|uuid|exists:sessions,id',
            'term_id' => 'required|uuid|exists:terms,id',
            'fee_item_id' => 'required|uuid|exists:fee_items,id',
            'amount' => 'required|numeric|min:0',
            'is_mandatory' => 'boolean',
        ]);

        // Check for duplicate
        $exists = FeeStructure::where('scope', FeeStructure::SCOPE_CLASS)
            ->where('school_class_id', $validated['class_id'])
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->where('fee_item_id', $validated['fee_item_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'fee_structure' => ['A fee structure already exists for this class, session, term, and fee item combination.'],
            ]);
        }

        $validated['school_class_id'] = $validated['class_id'];
        unset($validated['class_id']);

        $validated['school_id'] = $school->id;
        $validated['scope'] = FeeStructure::SCOPE_CLASS;
        $validated['created_by'] = $request->user()->id;

        $feeStructure = FeeStructure::create($validated);

        return response()->json([
            'data' => $feeStructure->load(['class', 'session', 'term', 'feeItem']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/structures/{feeStructure}",
     *     tags={"school-v2.4","school-v2.0"},
     *     summary="Get fee structure",
     *
     *     @OA\Parameter(name="feeStructure", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Fee structure returned"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, FeeStructure $feeStructure)
    {
        $this->ensurePermission($request, 'finance.fee-structures.view');

        if ($feeStructure->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json([
            'data' => $feeStructure->load(['class', 'session', 'term', 'feeItem']),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/fees/structures/{feeStructure}",
     *     tags={"school-v2.4","school-v2.0"},
     *     summary="Update fee structure",
     *
     *     @OA\Parameter(name="feeStructure", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, FeeStructure $feeStructure)
    {
        $this->ensurePermission($request, 'finance.fee-structures.update');

        if ($feeStructure->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'is_mandatory' => 'boolean',
        ]);

        $feeStructure->update($validated);

        return response()->json([
            'data' => $feeStructure->fresh()->load(['class', 'session', 'term', 'feeItem']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/fees/structures/{feeStructure}",
     *     tags={"school-v2.4","school-v2.0"},
     *     summary="Delete fee structure",
     *
     *     @OA\Parameter(name="feeStructure", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, FeeStructure $feeStructure)
    {
        $this->ensurePermission($request, 'finance.fee-structures.delete');

        if ($feeStructure->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $feeStructure->delete();

        return response()->json(null, 204);
    }

    /**
     * Get total amount for a specific class, session, and term.
     */
    public function getTotal(Request $request)
    {
        $this->ensurePermission($request, 'finance.fee-structures.view');

        $validated = $request->validate([
            'class_id' => 'required|uuid|exists:classes,id',
            'session_id' => 'required|uuid|exists:sessions,id',
            'term_id' => 'required|uuid|exists:terms,id',
        ]);

        $total = FeeStructure::where('school_id', $request->user()->school_id)
            ->where('scope', FeeStructure::SCOPE_CLASS)
            ->where('school_class_id', $validated['class_id'])
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->sum('amount');

        $breakdown = FeeStructure::where('school_id', $request->user()->school_id)
            ->where('scope', FeeStructure::SCOPE_CLASS)
            ->where('school_class_id', $validated['class_id'])
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->with('feeItem')
            ->get()
            ->map(function ($structure) {
                return [
                    'fee_item' => $structure->feeItem->name,
                    'amount' => $structure->amount,
                    'is_mandatory' => $structure->is_mandatory,
                ];
            });

        return response()->json([
            'data' => [
                'total' => $total,
                'breakdown' => $breakdown,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/fees/structures/copy",
     *     tags={"school-v2.4","school-v2.0"},
     *     summary="Copy fee structures between sessions/terms",
     *
     *     @OA\Response(response=201, description="Structures copied"),
     *     @OA\Response(response=404, description="Source not found")
     * )
     */
    public function copy(Request $request)
    {
        $this->ensurePermission($request, 'finance.fee-structures.copy');

        $school = $request->user()->school;

        if (! $school) {
            return response()->json([
                'message' => 'Authenticated user is not associated with any school.',
            ], 422);
        }

        $validated = $request->validate([
            'from_class_id' => 'required|uuid|exists:classes,id',
            'from_session_id' => 'required|uuid|exists:sessions,id',
            'from_term_id' => 'required|uuid|exists:terms,id',
            'to_class_id' => 'required|uuid|exists:classes,id',
            'to_session_id' => 'required|uuid|exists:sessions,id',
            'to_term_id' => 'required|uuid|exists:terms,id',
        ]);

        // Get source fee structures
        $sourceFeeStructures = FeeStructure::where('school_id', $school->id)
            ->where('scope', FeeStructure::SCOPE_CLASS)
            ->where('school_class_id', $validated['from_class_id'])
            ->where('session_id', $validated['from_session_id'])
            ->where('term_id', $validated['from_term_id'])
            ->get();

        if ($sourceFeeStructures->isEmpty()) {
            return response()->json([
                'message' => 'No fee structures found for the specified source class, session, and term.',
            ], 404);
        }

        $created = [];
        $skipped = [];

        DB::beginTransaction();
        try {
            foreach ($sourceFeeStructures as $source) {
                // Check if destination already exists
                $exists = FeeStructure::where('scope', FeeStructure::SCOPE_CLASS)
                    ->where('school_class_id', $validated['to_class_id'])
                    ->where('session_id', $validated['to_session_id'])
                    ->where('term_id', $validated['to_term_id'])
                    ->where('fee_item_id', $source->fee_item_id)
                    ->exists();

                if ($exists) {
                    $skipped[] = $source->feeItem->name;

                    continue;
                }

                $newStructure = FeeStructure::create([
                    'school_id' => $school->id,
                    'scope' => FeeStructure::SCOPE_CLASS,
                    'school_class_id' => $validated['to_class_id'],
                    'created_by' => $request->user()->id,
                    'session_id' => $validated['to_session_id'],
                    'term_id' => $validated['to_term_id'],
                    'fee_item_id' => $source->fee_item_id,
                    'amount' => $source->amount,
                    'is_mandatory' => $source->is_mandatory,
                ]);

                $created[] = $newStructure->load(['class', 'session', 'term', 'feeItem']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Fee structures copied successfully.',
                'data' => [
                    'created' => $created,
                    'skipped' => $skipped,
                    'created_count' => count($created),
                    'skipped_count' => count($skipped),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to copy fee structures.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/fees/structures/by-session-term",
     *     tags={"school-v2.4","school-v2.0"},
     *     summary="Fee structures by session and term",
     *
     *     @OA\Response(response=200, description="Fee structures returned")
     * )
     */
    public function getBySessionTerm(Request $request)
    {
        $this->ensurePermission($request, 'finance.fee-structures.view');

        $validated = $request->validate([
            'session_id' => 'required|uuid|exists:sessions,id',
            'term_id' => 'required|uuid|exists:terms,id',
        ]);

        $feeStructures = FeeStructure::where('school_id', $request->user()->school_id)
            ->where('scope', FeeStructure::SCOPE_CLASS)
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->with(['class', 'feeItem'])
            ->get()
            ->groupBy('school_class_id')
            ->map(function ($structures, $classId) {
                $class = $structures->first()->class;
                $total = $structures->sum('amount');

                return [
                    'class' => $class,
                    'total_amount' => $total,
                    'fee_items' => $structures->map(function ($structure) {
                        return [
                            'id' => $structure->id,
                            'fee_item' => $structure->feeItem,
                            'amount' => $structure->amount,
                            'is_mandatory' => $structure->is_mandatory,
                        ];
                    }),
                ];
            })
            ->values();

        return response()->json([
            'data' => $feeStructures,
        ]);
    }
}
