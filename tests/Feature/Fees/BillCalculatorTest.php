<?php

use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StudentBill;
use App\Services\Fees\BillCalculator;
use App\Services\Fees\BillGenerationService;
use App\Services\Fees\FeeAssignmentService;
use Tests\Support\FeesFixture;

beforeEach(function () {
    $this->fx = FeesFixture::make();
    $this->calculator = app(BillCalculator::class);

    // One student, one 110,000 bill -- the example from the specification.
    app(FeeAssignmentService::class)->create($this->fx['school'], [
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => $this->fx['jss2']->id,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Tuition')->id,
        'amount' => '110000',
    ], $this->fx['admin']);

    app(BillGenerationService::class)->generateForSchool(
        $this->fx['school']->id,
        $this->fx['session']->id,
        $this->fx['term']->id,
    );

    $this->bill = StudentBill::where('student_id', $this->fx['john']->id)->firstOrFail();
});

function recordPayment(string $amount, string $status, array $overrides = []): Payment
{
    static $sequence = 0;
    $sequence++;

    return Payment::create(array_merge([
        'school_id' => test()->fx['school']->id,
        'student_id' => test()->fx['john']->id,
        'session_id' => test()->fx['session']->id,
        'term_id' => test()->fx['term']->id,
        'student_bill_id' => test()->bill->id,
        'reference' => 'CYF-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT).'-'.uniqid(),
        'amount' => $amount,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'source' => Payment::SOURCE_STUDENT_SUBMISSION,
        'status' => $status,
    ], $overrides));
}

it('reports an untouched bill as unpaid', function () {
    $totals = $this->calculator->forBill($this->bill);

    expect($totals['total'])->toBe('110000.00')
        ->and($totals['verified_paid'])->toBe('0.00')
        ->and($totals['pending'])->toBe('0.00')
        ->and($totals['outstanding'])->toBe('110000.00')
        ->and($totals['status'])->toBe(BillCalculator::STATUS_UNPAID);
});

it('does not let submitted evidence reduce what a student owes', function () {
    recordPayment('20000', Payment::STATUS_PENDING);

    $totals = $this->calculator->forBill($this->bill);

    // This is the whole point of the verification workflow: the money is
    // visible as pending, but the balance has not moved.
    expect($totals['pending'])->toBe('20000.00')
        ->and($totals['verified_paid'])->toBe('0.00')
        ->and($totals['outstanding'])->toBe('110000.00')
        ->and($totals['status'])->toBe(BillCalculator::STATUS_PENDING);
});

it('counts a verified payment and reports the rest as outstanding', function () {
    recordPayment('50000', Payment::STATUS_VERIFIED);
    recordPayment('20000', Payment::STATUS_PENDING);

    $totals = $this->calculator->forBill($this->bill);

    expect($totals['verified_paid'])->toBe('50000.00')
        ->and($totals['pending'])->toBe('20000.00')
        ->and($totals['outstanding'])->toBe('60000.00')
        ->and($totals['status'])->toBe(BillCalculator::STATUS_PARTIALLY_PAID);
});

it('clears a bill once verified payments cover it', function () {
    recordPayment('110000', Payment::STATUS_VERIFIED);

    $totals = $this->calculator->forBill($this->bill);

    expect($totals['outstanding'])->toBe('0.00')
        ->and($totals['status'])->toBe(BillCalculator::STATUS_PAID);
});

it('reports an overpayment instead of a negative balance', function () {
    recordPayment('130000', Payment::STATUS_VERIFIED);

    $totals = $this->calculator->forBill($this->bill);

    expect($totals['outstanding'])->toBe('0.00')
        ->and($totals['overpaid_amount'])->toBe('20000.00')
        ->and($totals['status'])->toBe(BillCalculator::STATUS_OVERPAID);
});

it('ignores rejected and reversed payments', function () {
    recordPayment('50000', Payment::STATUS_REJECTED);
    recordPayment('50000', Payment::STATUS_REVERSED);

    $totals = $this->calculator->forBill($this->bill);

    expect($totals['verified_paid'])->toBe('0.00')
        ->and($totals['pending'])->toBe('0.00')
        ->and($totals['outstanding'])->toBe('110000.00')
        ->and($totals['status'])->toBe(BillCalculator::STATUS_UNPAID);
});

it('credits a verified payment in full even when it is only part-allocated', function () {
    $payment = recordPayment('50000', Payment::STATUS_VERIFIED);
    $item = $this->bill->items()->firstOrFail();

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'student_bill_item_id' => $item->id,
        'amount' => '40000',
    ]);

    $totals = $this->calculator->forBill($this->bill);

    // The balance follows the verified payment, not the allocation; the
    // remaining 10,000 is a credit, not money the student still owes.
    expect($totals['verified_paid'])->toBe('50000.00')
        ->and($totals['outstanding'])->toBe('60000.00')
        ->and($payment->unallocatedAmount())->toBe('10000.00')
        ->and($this->calculator->paidPerItem($this->bill->id)[$item->id])->toBe('40000.00');
});

it('leaves a fee unpaid when only a pending payment is allocated against it', function () {
    $payment = recordPayment('50000', Payment::STATUS_PENDING);
    $item = $this->bill->items()->firstOrFail();

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'student_bill_item_id' => $item->id,
        'amount' => '50000',
    ]);

    expect($this->calculator->paidPerItem($this->bill->id))->toBe([]);
});

it('does not call an empty bill paid', function () {
    $this->bill->items()->delete();

    expect($this->calculator->forBill($this->bill)['status'])
        ->toBe(BillCalculator::STATUS_UNPAID);
});

it('gives the same answer in bulk as one at a time', function () {
    recordPayment('50000', Payment::STATUS_VERIFIED);

    $bills = StudentBill::query()->get();
    $bulk = $this->calculator->forBills($bills);

    expect($bulk)->toHaveCount($bills->count())
        ->and($bulk[$this->bill->id])->toBe($this->calculator->forBill($this->bill));
});

it('counts money recorded before the bill existed', function () {
    recordPayment('50000', Payment::STATUS_VERIFIED, ['student_bill_id' => null]);

    expect($this->calculator->forBill($this->bill)['verified_paid'])->toBe('50000.00');
});
