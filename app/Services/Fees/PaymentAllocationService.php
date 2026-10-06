<?php

namespace App\Services\Fees;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StudentBillItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deciding which fees a verified payment covered.
 *
 * Allocation is bookkeeping, not balance: a student's outstanding follows the
 * verified payment total, so a payment that is only half allocated still
 * credits them in full. What allocation answers is "what did this ₦50,000
 * pay for", and that is what a receipt and a per-fee report read from.
 */
class PaymentAllocationService
{
    public function __construct(private readonly FinanceAuditLogger $audit) {}

    /**
     * Replace a payment's allocations wholesale.
     *
     * Deliberately not an incremental edit: re-deriving the whole set inside
     * one transaction means a partial write can never leave the payment
     * over-allocated between two statements.
     *
     * @param  array<int, array{student_bill_item_id: string, amount: string|float|int}>  $allocations
     */
    public function allocate(Payment $payment, array $allocations, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($payment, $allocations, $actor) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $payment->isVerified()) {
                throw ValidationException::withMessages([
                    'payment' => ['Only a verified payment can be allocated.'],
                ]);
            }

            $before = $this->snapshot($payment);

            $rows = $this->validate($payment, $allocations);

            $payment->allocations()->delete();

            foreach ($rows as $row) {
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'student_bill_item_id' => $row['student_bill_item_id'],
                    'amount' => $row['amount'],
                    'allocated_by' => $actor?->id,
                ]);
            }

            $this->audit->log(
                $payment->school_id,
                'payment.allocated',
                'payment',
                $payment->id,
                $before,
                $this->snapshot($payment->fresh()),
                $actor,
            );

            return $payment->fresh('allocations');
        });
    }

    /**
     * Spread a payment across the student's unpaid fees, oldest first.
     *
     * This is what runs on approval so a verified payment is never left
     * sitting unexplained. An admin can re-allocate it afterwards.
     */
    public function autoAllocate(Payment $payment, ?User $actor = null): Payment
    {
        $remaining = Money::of($payment->amount);
        $allocations = [];

        foreach ($this->outstandingItems($payment) as $item) {
            if (Money::isZero($remaining)) {
                break;
            }

            $owing = $this->owingOn($item, $payment->id);

            if (Money::compare($owing, '0') <= 0) {
                continue;
            }

            $take = Money::min($owing, $remaining);

            $allocations[] = [
                'student_bill_item_id' => $item->id,
                'amount' => $take,
            ];

            $remaining = Money::sub($remaining, $take);
        }

        // An overpayment, or a payment made before any bill exists, simply has
        // nothing to point at. That is a credit, not a failure.
        if ($allocations === []) {
            return $payment;
        }

        return $this->allocate($payment, $allocations, $actor);
    }

    /**
     * @param  array<int, array<string, mixed>>  $allocations
     * @return array<int, array{student_bill_item_id: string, amount: string}>
     */
    private function validate(Payment $payment, array $allocations): array
    {
        $rows = [];
        $seen = [];
        $total = '0.00';

        foreach ($allocations as $allocation) {
            $itemId = (string) ($allocation['student_bill_item_id'] ?? '');
            $amount = Money::of($allocation['amount'] ?? 0);

            if (Money::compare($amount, '0') <= 0) {
                throw ValidationException::withMessages([
                    'allocations' => ['Every allocation must be greater than zero.'],
                ]);
            }

            if (isset($seen[$itemId])) {
                throw ValidationException::withMessages([
                    'allocations' => ['Each fee can only appear once in an allocation.'],
                ]);
            }
            $seen[$itemId] = true;

            $item = StudentBillItem::query()
                ->whereKey($itemId)
                ->where('school_id', $payment->school_id)
                ->whereHas('bill', fn ($bill) => $bill->where('student_id', $payment->student_id))
                ->first();

            if (! $item) {
                throw ValidationException::withMessages([
                    'allocations' => ['One of the selected fees does not belong to this student.'],
                ]);
            }

            $owing = $this->owingOn($item, $payment->id);

            if (Money::greaterThan($amount, $owing)) {
                throw ValidationException::withMessages([
                    'allocations' => ["You cannot allocate more than is owed on {$item->name}."],
                ]);
            }

            $total = Money::add($total, $amount);
            $rows[] = ['student_bill_item_id' => $item->id, 'amount' => $amount];
        }

        if (Money::greaterThan($total, $payment->amount)) {
            throw ValidationException::withMessages([
                'allocations' => ['You cannot allocate more than the payment is worth.'],
            ]);
        }

        return $rows;
    }

    /**
     * What is still owed on a line, ignoring this payment's own existing
     * allocations so a re-allocation is not blocked by itself.
     */
    private function owingOn(StudentBillItem $item, string $excludingPaymentId): string
    {
        $allocated = PaymentAllocation::query()
            ->where('student_bill_item_id', $item->id)
            ->where('payment_id', '!=', $excludingPaymentId)
            ->whereHas('payment', fn ($payment) => $payment->where('status', Payment::STATUS_VERIFIED))
            ->sum('amount');

        return Money::atLeastZero(Money::sub($item->net_amount, $allocated));
    }

    /**
     * @return \Illuminate\Support\Collection<int, StudentBillItem>
     */
    private function outstandingItems(Payment $payment)
    {
        return StudentBillItem::query()
            ->whereHas('bill', fn ($bill) => $bill
                ->where('student_id', $payment->student_id)
                ->where('session_id', $payment->session_id)
                ->where('term_id', $payment->term_id))
            ->where('is_removed', false)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Payment $payment): array
    {
        return [
            'allocations' => $payment->allocations()
                ->get(['student_bill_item_id', 'amount'])
                ->map(fn ($allocation) => [
                    'student_bill_item_id' => $allocation->student_bill_item_id,
                    'amount' => (string) $allocation->amount,
                ])->all(),
        ];
    }
}
