<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeeItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="school-v2.4",
 *     description="Fee Management"
 * )
 */
class FeeItemController extends Controller
{
    /**
     * Display a listing of fee items.
     */
    public function index(Request $request)
    {
        $perPage = max((int) $request->input('per_page', 10), 1);

        $feeItems = $request->user()->school->feeItems()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->category);
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($feeItems);
    }

    /**
     * Store a newly created fee item.
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fee_items')->where('school_id', $school->id)
            ],
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'default_amount' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['school_id'] = $school->id;

        $feeItem = FeeItem::create($validated);

        return response()->json([
            'data' => $feeItem,
        ], 201);
    }

    /**
     * Display the specified fee item.
     */
    public function show(Request $request, FeeItem $feeItem)
    {
        if ($feeItem->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json([
            'data' => $feeItem,
        ]);
    }

    /**
     * Update the specified fee item.
     */
    public function update(Request $request, FeeItem $feeItem)
    {
        if ($feeItem->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fee_items')->where('school_id', $feeItem->school_id)->ignore($feeItem->id)
            ],
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'default_amount' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $feeItem->update($validated);

        return response()->json([
            'data' => $feeItem->fresh(),
        ]);
    }

    /**
     * Remove the specified fee item.
     */
    public function destroy(Request $request, FeeItem $feeItem)
    {
        if ($feeItem->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Check if fee item is used in any fee structures
        if ($feeItem->feeStructures()->exists()) {
            return response()->json([
                'message' => 'Cannot delete fee item that is used in fee structures.',
            ], 409);
        }

        $feeItem->delete();

        return response()->json(null, 204);
    }
}
