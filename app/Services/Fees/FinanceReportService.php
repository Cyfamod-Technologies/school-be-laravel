<?php

namespace App\Services\Fees;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StudentBill;
use App\Models\StudentBillItem;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * The numbers behind the finance dashboard and its reports.
 *
 * Everything here is an aggregate query, never a loop over BillCalculator --
 * a school with a few thousand students needs the overview to stay a handful
 * of queries, not one per student.
 *
 * Filters accepted throughout: session_id, term_id, school_class_id,
 * class_arm_id (all narrow which bills are in scope), fee_item_id (narrows
 * "expected"/"verified" to one fee -- see the note on `pending` below),
 * from/to (a date range applied to *when a payment was made*, not to the
 * bill itself -- a bill has no date of its own), and status (only meaningful
 * for `outstandingStudents()`, which is the one report that lists individual
 * students rather than a total).
 */
class FinanceReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{expected: string, verified: string, pending: string, outstanding: string, students_billed: int}
     */
    public function overview(string $schoolId, array $filters = []): array
    {
        $bills = $this->scopedBills($schoolId, $filters);
        $feeItemId = $filters['fee_item_id'] ?? null;

        $expected = $this->expectedTotal($bills, $feeItemId);
        $verified = $this->verifiedTotal($bills, $feeItemId, $filters['from'] ?? null, $filters['to'] ?? null);
        // Deliberately never scoped by fee_item_id: a submission is not yet
        // allocated to a specific fee, so there is no correct way to say how
        // much of it is "toward Tuition" until an admin verifies it.
        $pending = $this->pendingTotal($bills, $filters['from'] ?? null, $filters['to'] ?? null);

        return [
            'expected' => $expected,
            'verified' => $verified,
            'pending' => $pending,
            'outstanding' => Money::atLeastZero(Money::sub($expected, $verified)),
            'students_billed' => $bills->count(),
        ];
    }

    /**
     * Verified collections grouped by class, for "which class has paid".
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{school_class_id: ?string, class_name: string, expected: string, verified: string, students: int}>
     */
    public function collectionsByClass(string $schoolId, array $filters = []): array
    {
        $bills = $this->scopedBills($schoolId, $filters, with: ['schoolClass:id,name']);
        $feeItemId = $filters['fee_item_id'] ?? null;

        $expectedByBill = $this->expectedByBillIndex($bills, $feeItemId);
        $verifiedByBill = $this->verifiedByBillIndex($bills, $feeItemId, $filters['from'] ?? null, $filters['to'] ?? null);

        $rows = [];

        foreach ($bills->groupBy('school_class_id') as $classId => $classBills) {
            $expected = '0.00';
            $verified = '0.00';

            foreach ($classBills as $bill) {
                $expected = Money::add($expected, $expectedByBill[$bill->id] ?? 0);
                $verified = Money::add($verified, $verifiedByBill[$bill->id] ?? 0);
            }

            $rows[] = [
                'school_class_id' => $classId ?: null,
                'class_name' => $classBills->first()->schoolClass?->name ?? 'Unassigned',
                'expected' => $expected,
                'verified' => $verified,
                'students' => $classBills->count(),
            ];
        }

        return collect($rows)->sortBy('class_name')->values()->all();
    }

    /**
     * Students with money still owing, for the outstanding-fees report.
     *
     * `status` narrows the list to one derived payment status (unpaid,
     * partially_paid, pending_verification, overpaid) instead of every
     * student who owes something -- the only filter here that makes sense
     * per-student rather than as a total.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function outstandingStudents(string $schoolId, array $filters = []): array
    {
        $bills = $this->scopedBills($schoolId, $filters, with: [
            'student:id,admission_no,first_name,last_name',
            'schoolClass:id,name',
            'classArm:id,name',
        ]);

        $calculator = app(BillCalculator::class);
        $totals = $calculator->forBills($bills);
        $status = $filters['status'] ?? null;

        return $bills
            ->filter(function (StudentBill $bill) use ($totals, $status) {
                $billStatus = $totals[$bill->id]['status'] ?? null;

                if ($status) {
                    return $billStatus === $status;
                }

                return Money::greaterThan($totals[$bill->id]['outstanding'] ?? '0', '0');
            })
            ->map(fn (StudentBill $bill) => [
                'student_bill_id' => $bill->id,
                'student_id' => $bill->student_id,
                'student_name' => trim($bill->student->first_name.' '.$bill->student->last_name),
                'admission_no' => $bill->student->admission_no,
                'class_name' => $bill->schoolClass?->name,
                'class_arm_name' => $bill->classArm?->name,
                'total' => $totals[$bill->id]['total'],
                'verified_paid' => $totals[$bill->id]['verified_paid'],
                'outstanding' => $totals[$bill->id]['outstanding'],
                'status' => $totals[$bill->id]['status'],
            ])
            ->sortByDesc(fn ($row) => (float) $row['outstanding'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function scopedBills(string $schoolId, array $filters, array $with = []): Collection
    {
        return StudentBill::query()
            ->where('school_id', $schoolId)
            ->when(! empty($filters['session_id']), fn ($q) => $q->where('session_id', $filters['session_id']))
            ->when(! empty($filters['term_id']), fn ($q) => $q->where('term_id', $filters['term_id']))
            ->when(! empty($filters['school_class_id']), fn ($q) => $q->where('school_class_id', $filters['school_class_id']))
            ->when(! empty($filters['class_arm_id']), fn ($q) => $q->where('class_arm_id', $filters['class_arm_id']))
            ->with($with)
            ->get();
    }

    private function expectedTotal(Collection $bills, ?string $feeItemId): string
    {
        if ($bills->isEmpty()) {
            return '0.00';
        }

        return Money::of(
            StudentBillItem::query()
                ->whereIn('student_bill_id', $bills->pluck('id'))
                ->where('is_removed', false)
                ->when($feeItemId, fn ($q) => $q->where('fee_item_id', $feeItemId))
                ->sum('net_amount')
        );
    }

    private function verifiedTotal(Collection $bills, ?string $feeItemId, ?string $from, ?string $to): string
    {
        if ($bills->isEmpty()) {
            return '0.00';
        }

        if ($feeItemId) {
            return Money::of(
                PaymentAllocation::query()
                    ->join('student_bill_items', 'student_bill_items.id', '=', 'payment_allocations.student_bill_item_id')
                    ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                    ->whereIn('student_bill_items.student_bill_id', $bills->pluck('id'))
                    ->where('student_bill_items.fee_item_id', $feeItemId)
                    ->where('payments.status', Payment::STATUS_VERIFIED)
                    ->when($from, fn ($q) => $q->whereDate('payments.paid_at', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('payments.paid_at', '<=', $to))
                    ->sum('payment_allocations.amount')
            );
        }

        return $this->paymentStatusTotal($bills, Payment::STATUS_VERIFIED, $from, $to);
    }

    private function pendingTotal(Collection $bills, ?string $from, ?string $to): string
    {
        return $this->paymentStatusTotal($bills, Payment::STATUS_PENDING, $from, $to);
    }

    private function paymentStatusTotal(Collection $bills, string $status, ?string $from, ?string $to): string
    {
        if ($bills->isEmpty()) {
            return '0.00';
        }

        return Money::of(
            Payment::query()
                ->whereIn('student_id', $bills->pluck('student_id')->unique())
                ->whereIn('session_id', $bills->pluck('session_id')->unique())
                ->whereIn('term_id', $bills->pluck('term_id')->unique())
                ->where('status', $status)
                ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
                ->sum('amount')
        );
    }

    /**
     * @return array<string, string> keyed by student_bill_id
     */
    private function expectedByBillIndex(Collection $bills, ?string $feeItemId): array
    {
        if ($bills->isEmpty()) {
            return [];
        }

        $rows = StudentBillItem::query()
            ->whereIn('student_bill_id', $bills->pluck('id'))
            ->where('is_removed', false)
            ->when($feeItemId, fn ($q) => $q->where('fee_item_id', $feeItemId))
            ->selectRaw('student_bill_id, SUM(net_amount) as total_amount')
            ->groupBy('student_bill_id')
            ->pluck('total_amount', 'student_bill_id');

        return collect($rows)->map(fn ($amount) => Money::of($amount))->all();
    }

    /**
     * @return array<string, string> keyed by student_bill_id
     */
    private function verifiedByBillIndex(Collection $bills, ?string $feeItemId, ?string $from, ?string $to): array
    {
        if ($bills->isEmpty()) {
            return [];
        }

        if ($feeItemId) {
            $rows = PaymentAllocation::query()
                ->join('student_bill_items', 'student_bill_items.id', '=', 'payment_allocations.student_bill_item_id')
                ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                ->whereIn('student_bill_items.student_bill_id', $bills->pluck('id'))
                ->where('student_bill_items.fee_item_id', $feeItemId)
                ->where('payments.status', Payment::STATUS_VERIFIED)
                ->when($from, fn ($q) => $q->whereDate('payments.paid_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('payments.paid_at', '<=', $to))
                ->selectRaw('student_bill_items.student_bill_id as bill_id, SUM(payment_allocations.amount) as total_amount')
                ->groupBy('student_bill_items.student_bill_id')
                ->pluck('total_amount', 'bill_id');

            return collect($rows)->map(fn ($amount) => Money::of($amount))->all();
        }

        // Without a fee filter, a payment (not an allocation) is the right
        // unit -- allocation is optional and a payment still counts in full
        // even if it was never broken down by fee (see BillCalculator).
        $rows = Payment::query()
            ->whereIn('student_id', $bills->pluck('student_id')->unique())
            ->whereIn('session_id', $bills->pluck('session_id')->unique())
            ->whereIn('term_id', $bills->pluck('term_id')->unique())
            ->where('status', Payment::STATUS_VERIFIED)
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->selectRaw('student_id, session_id, term_id, SUM(amount) as total_amount')
            ->groupBy('student_id', 'session_id', 'term_id')
            ->get();

        $byComposite = [];
        foreach ($rows as $row) {
            $byComposite[$row->student_id.'|'.$row->session_id.'|'.$row->term_id] = Money::of($row->total_amount);
        }

        $byBillId = [];
        foreach ($bills as $bill) {
            $key = $bill->student_id.'|'.$bill->session_id.'|'.$bill->term_id;
            $byBillId[$bill->id] = $byComposite[$key] ?? '0.00';
        }

        return $byBillId;
    }
}
