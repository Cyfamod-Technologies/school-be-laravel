<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeeAdjustment;
use App\Models\StudentBillItem;
use App\Services\Fees\FeeAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Discounts, surcharges and waivers on a student's bill (§18).
 *
 * @OA\Tag(
 *     name="school-v2.5",
 *     description="Fees - assignments and bills"
 * )
 */
class FeeAdjustmentController extends Controller
{
    public function __construct(private readonly FeeAdjustmentService $adjustments) {}

    /**
     * @OA\Post(
     *     path="/api/v1/fees/bill-items/{studentBillItem}/adjustments",
     *     tags={"school-v2.5"},
     *     summary="Apply a discount, surcharge or waiver to one bill line",
     *
     *     @OA\Response(response=201, description="Applied"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, StudentBillItem $studentBillItem)
    {
        $this->ensurePermission($request, 'finance.bill-items.adjust');

        if ($studentBillItem->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(FeeAdjustment::TYPES)],
            // A waiver derives its own amount from what remains on the line.
            'amount' => 'required_unless:type,waiver|nullable|numeric|min:0.01',
            'reason' => 'required|string|min:3|max:500',
        ]);

        $adjustment = $this->adjustments->apply($studentBillItem, $validated, $request->user());

        return response()->json([
            'message' => 'Adjustment applied.',
            'data' => [
                'adjustment_id' => $adjustment->id,
                'type' => $adjustment->type,
                'amount' => (string) $adjustment->amount,
                'reason' => $adjustment->reason,
                'bill_item' => $studentBillItem->fresh()->only([
                    'id', 'amount', 'discount_amount', 'surcharge_amount', 'net_amount',
                ]),
            ],
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/fees/adjustments/{feeAdjustment}",
     *     tags={"school-v2.5"},
     *     summary="Reverse an adjustment",
     *     description="The record is kept, marked reversed, for audit purposes.",
     *
     *     @OA\Response(response=200, description="Reversed"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, FeeAdjustment $feeAdjustment)
    {
        $this->ensurePermission($request, 'finance.bill-items.adjust');

        if ($feeAdjustment->school_id !== $request->user()->school_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $this->adjustments->reverse($feeAdjustment, $request->user());

        return response()->json(['message' => 'Adjustment reversed.']);
    }
}
