<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BankDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="school-v2.4",
 *     description="Fee Management"
 * )
 */
class BankDetailController extends Controller
{
    /**
     * Display a listing of bank details.
     */
    public function index(Request $request)
    {
        $perPage = max((int) $request->input('per_page', 10), 1);

        $bankDetails = $request->user()->school->bankDetails()
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->filled('is_default'), function ($query) use ($request) {
                $query->where('is_default', filter_var($request->is_default, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($bankDetails);
    }

    /**
     * Store a newly created bank detail.
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
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'bank_code' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['school_id'] = $school->id;

        DB::beginTransaction();
        try {
            // If this is set as default, remove default from others
            if (isset($validated['is_default']) && $validated['is_default']) {
                BankDetail::where('school_id', $school->id)
                    ->update(['is_default' => false]);
            }

            $bankDetail = BankDetail::create($validated);

            DB::commit();

            return response()->json([
                'data' => $bankDetail,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create bank detail.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified bank detail.
     */
    public function show(Request $request, BankDetail $bankDetail)
    {
        if ($bankDetail->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json([
            'data' => $bankDetail,
        ]);
    }

    /**
     * Update the specified bank detail.
     */
    public function update(Request $request, BankDetail $bankDetail)
    {
        if ($bankDetail->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'bank_code' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            // If this is set as default, remove default from others
            if (isset($validated['is_default']) && $validated['is_default']) {
                BankDetail::where('school_id', $bankDetail->school_id)
                    ->where('id', '!=', $bankDetail->id)
                    ->update(['is_default' => false]);
            }

            $bankDetail->update($validated);

            DB::commit();

            return response()->json([
                'data' => $bankDetail->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update bank detail.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified bank detail.
     */
    public function destroy(Request $request, BankDetail $bankDetail)
    {
        if ($bankDetail->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $bankDetail->delete();

        return response()->json(null, 204);
    }

    /**
     * Set a bank detail as default.
     */
    public function setDefault(Request $request, BankDetail $bankDetail)
    {
        if ($bankDetail->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        DB::beginTransaction();
        try {
            // Remove default from all other bank details
            BankDetail::where('school_id', $bankDetail->school_id)
                ->update(['is_default' => false]);

            // Set this one as default
            $bankDetail->update(['is_default' => true]);

            DB::commit();

            return response()->json([
                'data' => $bankDetail->fresh(),
                'message' => 'Bank detail set as default successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to set default bank detail.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the default bank detail.
     */
    public function getDefault(Request $request)
    {
        $bankDetail = BankDetail::where('school_id', $request->user()->school_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (!$bankDetail) {
            return response()->json([
                'message' => 'No default bank detail found.',
            ], 404);
        }

        return response()->json([
            'data' => $bankDetail,
        ]);
    }
}
