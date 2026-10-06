<?php

namespace App\Services\Fees;

use App\Models\FeeAdjustment;
use App\Models\StudentBillItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Discounts, surcharges and waivers applied to one bill line.
 *
 * Every write here is two things at once: a change to what the line is worth,
 * and an audit record of who changed it and why. `student_bill_items`'s
 * discount/surcharge columns are a projection of the `fee_adjustments` rows,
 * recomputed here rather than trusted as an independent figure.
 */
class FeeAdjustmentService
{
    public function __construct(private readonly FinanceAuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function apply(StudentBillItem $item, array $data, User $actor): FeeAdjustment
    {
        return DB::transaction(function () use ($item, $data, $actor) {
            $item = StudentBillItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($item->is_removed) {
                throw ValidationException::withMessages([
                    'student_bill_item' => ['This fee has been withdrawn from the bill and cannot be adjusted.'],
                ]);
            }

            $type = $data['type'];

            if ($type === FeeAdjustment::TYPE_WAIVER) {
                // A waiver forgives what is left owing on the line right now,
                // after any earlier adjustment -- not the original amount.
                $amount = Money::atLeastZero($item->net_amount);

                if (Money::isZero($amount)) {
                    throw ValidationException::withMessages([
                        'amount' => ['There is nothing left on this fee to waive.'],
                    ]);
                }
            } else {
                $amount = Money::of($data['amount']);

                if (Money::compare($amount, '0') <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => ['The adjustment amount must be greater than zero.'],
                    ]);
                }
            }

            $before = $this->snapshot($item);

            $adjustment = FeeAdjustment::create([
                'school_id' => $item->school_id,
                'student_bill_item_id' => $item->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => $data['reason'],
                'created_by' => $actor->id,
            ]);

            $this->recalculate($item);

            $this->audit->log(
                $item->school_id,
                'fee_adjustment.applied',
                'student_bill_item',
                $item->id,
                $before,
                $this->snapshot($item->fresh()),
                $actor,
            );

            return $adjustment;
        });
    }

    /**
     * Undo an adjustment. The row is kept -- reversed_at is when it stopped
     * counting, not when it happened -- and the line's totals are recomputed
     * from what remains.
     */
    public function reverse(FeeAdjustment $adjustment, User $actor): FeeAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor) {
            if ($adjustment->reversed_at) {
                throw ValidationException::withMessages([
                    'adjustment' => ['This adjustment has already been reversed.'],
                ]);
            }

            $item = StudentBillItem::query()->lockForUpdate()->findOrFail($adjustment->student_bill_item_id);
            $before = $this->snapshot($item);

            $adjustment->update([
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
            ]);

            $this->recalculate($item);

            $this->audit->log(
                $item->school_id,
                'fee_adjustment.reversed',
                'student_bill_item',
                $item->id,
                $before,
                $this->snapshot($item->fresh()),
                $actor,
            );

            return $adjustment->fresh();
        });
    }

    /**
     * Rebuild a line's discount/surcharge/net figures from its still-active
     * adjustments. `amount` (the assigned fee) never changes here.
     */
    private function recalculate(StudentBillItem $item): void
    {
        $active = $item->adjustments()->active()->get();

        $discount = Money::sum(
            $active->filter(fn (FeeAdjustment $a) => $a->isReducing())->pluck('amount')
        );
        $surcharge = Money::sum(
            $active->filter(fn (FeeAdjustment $a) => ! $a->isReducing())->pluck('amount')
        );

        $net = Money::atLeastZero(Money::add(Money::sub($item->amount, $discount), $surcharge));

        $item->update([
            'discount_amount' => $discount,
            'surcharge_amount' => $surcharge,
            'net_amount' => $net,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(StudentBillItem $item): array
    {
        return [
            'amount' => (string) $item->amount,
            'discount_amount' => (string) $item->discount_amount,
            'surcharge_amount' => (string) $item->surcharge_amount,
            'net_amount' => (string) $item->net_amount,
        ];
    }
}
