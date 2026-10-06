<?php

namespace App\Services\Fees;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StudentBill;
use App\Models\StudentBillItem;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * The one place a fee balance is computed.
 *
 * Both the student portal and the finance dashboard read their figures from
 * here, so the two can never quote different numbers for the same student.
 *
 * The rules, stated once:
 *
 *   total          = sum of the bill's live line totals
 *   verified_paid  = sum of VERIFIED payments for that student/session/term
 *   pending        = sum of payments still awaiting verification
 *   outstanding    = max(total - verified_paid, 0)
 *
 * Note what verified_paid is NOT derived from: allocations. Allocations say
 * which fee each naira covered, and an admin may approve 50,000 but only
 * allocate 40,000 of it. Deriving the balance from allocations would leave the
 * student short-credited by the difference; deriving it from verified payments
 * credits them in full and leaves the remainder as a visible unallocated
 * credit, which is what actually happened.
 */
class BillCalculator
{
    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PENDING = 'pending_verification';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERPAID = 'overpaid';

    public const STATUSES = [
        self::STATUS_UNPAID,
        self::STATUS_PENDING,
        self::STATUS_PARTIALLY_PAID,
        self::STATUS_PAID,
        self::STATUS_OVERPAID,
    ];

    /**
     * @return array{total: string, verified_paid: string, pending: string, outstanding: string, overpaid_amount: string, status: string}
     */
    public function forBill(StudentBill $bill): array
    {
        return $this->forStudentPeriod($bill->student_id, $bill->session_id, $bill->term_id, $bill->id);
    }

    /**
     * Totals for a student's period, whether or not a bill row exists yet --
     * a payment can legitimately land before the bill is generated.
     *
     * @return array{total: string, verified_paid: string, pending: string, outstanding: string, overpaid_amount: string, status: string}
     */
    public function forStudentPeriod(
        string $studentId,
        string $sessionId,
        string $termId,
        ?string $billId = null,
    ): array {
        $billId ??= StudentBill::query()
            ->where('student_id', $studentId)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->value('id');

        $total = $billId
            ? StudentBillItem::query()
                ->where('student_bill_id', $billId)
                ->where('is_removed', false)
                ->sum('net_amount')
            : 0;

        $paymentTotals = Payment::query()
            ->where('student_id', $studentId)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->whereIn('status', [Payment::STATUS_VERIFIED, Payment::STATUS_PENDING])
            ->selectRaw('status, SUM(amount) as total_amount')
            ->groupBy('status')
            ->pluck('total_amount', 'status');

        return $this->summarise(
            $total,
            $paymentTotals[Payment::STATUS_VERIFIED] ?? 0,
            $paymentTotals[Payment::STATUS_PENDING] ?? 0,
        );
    }

    /**
     * Totals for many bills in a fixed number of queries -- what the
     * outstanding-students report and the finance dashboard run on.
     *
     * @param  Collection<int, StudentBill>  $bills
     * @return array<string, array{total: string, verified_paid: string, pending: string, outstanding: string, overpaid_amount: string, status: string}>
     */
    public function forBills(Collection $bills): array
    {
        if ($bills->isEmpty()) {
            return [];
        }

        $billIds = $bills->pluck('id')->all();

        $totals = StudentBillItem::query()
            ->whereIn('student_bill_id', $billIds)
            ->where('is_removed', false)
            ->selectRaw('student_bill_id, SUM(net_amount) as total_amount')
            ->groupBy('student_bill_id')
            ->pluck('total_amount', 'student_bill_id');

        // Payments are matched on the period rather than on student_bill_id so
        // that money recorded before the bill existed still counts.
        $paymentRows = Payment::query()
            ->whereIn('student_id', $bills->pluck('student_id')->unique()->all())
            ->whereIn('session_id', $bills->pluck('session_id')->unique()->all())
            ->whereIn('term_id', $bills->pluck('term_id')->unique()->all())
            ->whereIn('status', [Payment::STATUS_VERIFIED, Payment::STATUS_PENDING])
            ->selectRaw('student_id, session_id, term_id, status, SUM(amount) as total_amount')
            ->groupBy('student_id', 'session_id', 'term_id', 'status')
            ->get();

        $payments = [];
        foreach ($paymentRows as $row) {
            $key = $row->student_id.'|'.$row->session_id.'|'.$row->term_id;
            $payments[$key][$row->status] = $row->total_amount;
        }

        $summaries = [];
        foreach ($bills as $bill) {
            $key = $bill->student_id.'|'.$bill->session_id.'|'.$bill->term_id;

            $summaries[(string) $bill->id] = $this->summarise(
                $totals[$bill->id] ?? 0,
                $payments[$key][Payment::STATUS_VERIFIED] ?? 0,
                $payments[$key][Payment::STATUS_PENDING] ?? 0,
            );
        }

        return $summaries;
    }

    /**
     * How much of each line has actually been paid for, from the allocations
     * of verified payments. This is the per-fee view; the balance above is the
     * per-student one.
     *
     * @return array<string, string> keyed by bill item id
     */
    public function paidPerItem(string $billId): array
    {
        $rows = PaymentAllocation::query()
            ->join('student_bill_items', 'student_bill_items.id', '=', 'payment_allocations.student_bill_item_id')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('student_bill_items.student_bill_id', $billId)
            ->where('payments.status', Payment::STATUS_VERIFIED)
            ->selectRaw('payment_allocations.student_bill_item_id as item_id, SUM(payment_allocations.amount) as total_amount')
            ->groupBy('payment_allocations.student_bill_item_id')
            ->pluck('total_amount', 'item_id');

        return collect($rows)->map(fn ($amount) => Money::of($amount))->all();
    }

    /**
     * @return array{total: string, verified_paid: string, pending: string, outstanding: string, overpaid_amount: string, status: string}
     */
    private function summarise(mixed $total, mixed $verifiedPaid, mixed $pending): array
    {
        $total = Money::of($total);
        $verifiedPaid = Money::of($verifiedPaid);
        $pending = Money::of($pending);

        return [
            'total' => $total,
            'verified_paid' => $verifiedPaid,
            'pending' => $pending,
            'outstanding' => Money::atLeastZero(Money::sub($total, $verifiedPaid)),
            'overpaid_amount' => Money::atLeastZero(Money::sub($verifiedPaid, $total)),
            'status' => $this->resolveStatus($total, $verifiedPaid, $pending),
        ];
    }

    private function resolveStatus(string $total, string $verifiedPaid, string $pending): string
    {
        if (Money::greaterThan($verifiedPaid, $total)) {
            return self::STATUS_OVERPAID;
        }

        // A zero bill with no payments is not "paid" -- there was nothing to
        // pay, and calling it paid would flatter the collection reports.
        if (! Money::isZero($total) && Money::compare($verifiedPaid, $total) >= 0) {
            return self::STATUS_PAID;
        }

        if (! Money::isZero($verifiedPaid)) {
            return self::STATUS_PARTIALLY_PAID;
        }

        if (! Money::isZero($pending)) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_UNPAID;
    }
}
