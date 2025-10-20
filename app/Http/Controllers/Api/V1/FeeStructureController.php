<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeeStructure;
use App\Models\FeeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="school-v2.4",
 *     description="Fee Management"
 * )
 */
class FeeStructureController extends Controller
{
    /**
     * Display a listing of fee structures.
     */
    public function index(Request $request)
    {
        $perPage = max((int) $request->input('per_page', 10), 1);

        $feeStructures = $request->user()->school->feeStructures()
            ->with(['class', 'session', 'term', 'feeItem'])
            ->when($request->filled('class_id'), function ($query) use ($request) {
                $query->where('class_id', $request->class_id);
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
     * Store a newly created fee structure.
     */
    public function store(Request $request)
    {
        $school = $request->user()->school;

        if (!$school) {
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
        $exists = FeeStructure::where('class_id', $validated['class_id'])
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->where('fee_item_id', $validated['fee_item_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'fee_structure' => ['A fee structure already exists for this class, session, term, and fee item combination.']
            ]);
        }

        $validated['school_id'] = $school->id;

        $feeStructure = FeeStructure::create($validated);

        return response()->json([
            'data' => $feeStructure->load(['class', 'session', 'term', 'feeItem']),
        ], 201);
    }

    /**
     * Display the specified fee structure.
     */
    public function show(Request $request, FeeStructure $feeStructure)
    {
        if ($feeStructure->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json([
            'data' => $feeStructure->load(['class', 'session', 'term', 'feeItem']),
        ]);
    }

    /**
     * Update the specified fee structure.
     */
    public function update(Request $request, FeeStructure $feeStructure)
    {
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
     * Remove the specified fee structure.
     */
    public function destroy(Request $request, FeeStructure $feeStructure)
    {
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
        $validated = $request->validate([
            'class_id' => 'required|uuid|exists:classes,id',
            'session_id' => 'required|uuid|exists:sessions,id',
            'term_id' => 'required|uuid|exists:terms,id',
        ]);

        $total = FeeStructure::where('school_id', $request->user()->school_id)
            ->where('class_id', $validated['class_id'])
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->sum('amount');

        $breakdown = FeeStructure::where('school_id', $request->user()->school_id)
            ->where('class_id', $validated['class_id'])
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
     * Copy fee structure from one term/session to another.
     */
    public function copy(Request $request)
    {
        $school = $request->user()->school;

        if (!$school) {
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
            ->where('class_id', $validated['from_class_id'])
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
                $exists = FeeStructure::where('class_id', $validated['to_class_id'])
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
                    'class_id' => $validated['to_class_id'],
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
     * Get fee structures grouped by class for a session and term.
     */
    public function getBySessionTerm(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|uuid|exists:sessions,id',
            'term_id' => 'required|uuid|exists:terms,id',
        ]);

        $feeStructures = FeeStructure::where('school_id', $request->user()->school_id)
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->with(['class', 'feeItem'])
            ->get()
            ->groupBy('class_id')
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
