<?php

use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StudentBill;
use App\Models\StudentBillItem;
use App\Services\Fees\BillGenerationService;
use App\Services\Fees\FeeAssignmentService;
use Tests\Support\FeesFixture;

beforeEach(function () {
    $this->fx = FeesFixture::make();
    $this->assignments = app(FeeAssignmentService::class);
    $this->bills = app(BillGenerationService::class);
});

function feeAssignment(array $data): FeeStructure
{
    return test()->assignments->create(test()->fx['school'], array_merge([
        'session_id' => test()->fx['session']->id,
        'term_id' => test()->fx['term']->id,
        'amount' => '1000',
    ], $data), test()->fx['admin']);
}

function generateBills(): array
{
    return test()->bills->generateForSchool(
        test()->fx['school']->id,
        test()->fx['session']->id,
        test()->fx['term']->id,
    );
}

function billFor(string $studentId): ?StudentBill
{
    return StudentBill::where('student_id', $studentId)
        ->where('session_id', test()->fx['session']->id)
        ->where('term_id', test()->fx['term']->id)
        ->first();
}

it('stacks every applicable scope onto one student bill', function () {
    // The worked example from the specification: John in JSS 2A.
    feeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Development Levy')->id,
        'amount' => '15000',
    ]);
    feeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => $this->fx['jss2']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Tuition')->id,
        'amount' => '80000',
    ]);
    feeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => $this->fx['jss2']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Examination Fee')->id,
        'amount' => '10000',
    ]);
    feeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS_ARM,
        'class_arm_id' => $this->fx['armA']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Practical Fee')->id,
        'amount' => '5000',
    ]);
    feeAssignment([
        'scope' => FeeStructure::SCOPE_STUDENT,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Excursion Fee')->id,
        'amount' => '25000',
        'student_ids' => [$this->fx['john']->id],
    ]);

    generateBills();

    $john = billFor($this->fx['john']->id);
    expect($john->items()->sum('net_amount'))->toEqual('135000.00');

    // Mary is JSS 2B: school-wide plus class fees only.
    expect(billFor($this->fx['mary']->id)->items()->sum('net_amount'))->toEqual('105000.00');

    // David is JSS 3: the school-wide levy and nothing else.
    expect(billFor($this->fx['david']->id)->items()->sum('net_amount'))->toEqual('15000.00');
});

it('produces one set of lines however many times it runs', function () {
    feeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Development Levy')->id,
        'amount' => '15000',
    ]);

    generateBills();
    $second = generateBills();

    expect(StudentBill::count())->toBe(3)
        ->and(StudentBillItem::count())->toBe(3)
        ->and($second['items_created'])->toBe(0);
});

it('brings a line back in step when the assignment amount changes', function () {
    $assignment = feeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Development Levy')->id,
        'amount' => '15000',
    ]);

    generateBills();

    $this->assignments->update($assignment, ['amount' => '18000'], $this->fx['admin']);
    $summary = generateBills();

    expect($summary['items_updated'])->toBe(3)
        ->and(billFor($this->fx['john']->id)->items()->sum('net_amount'))->toEqual('18000.00');
});

it('snapshots the fee name so a later rename does not rewrite history', function () {
    $item = FeesFixture::feeItem($this->fx['school'], 'Tuition');
    feeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => $item->id,
        'amount' => '80000',
    ]);

    generateBills();
    $item->update(['name' => 'Tuition (Revised)']);

    expect(billFor($this->fx['john']->id)->items()->first()->name)->toBe('Tuition');
});

it('drops a line when its assignment is deactivated and no money touched it', function () {
    $assignment = feeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Excursion Fee')->id,
        'amount' => '25000',
    ]);

    generateBills();
    expect(StudentBillItem::count())->toBe(3);

    $this->assignments->update($assignment, ['is_active' => false], $this->fx['admin']);
    $summary = generateBills();

    expect($summary['items_removed'])->toBe(3)
        ->and(StudentBillItem::count())->toBe(0);
});

it('retires rather than deletes a line that a payment was allocated to', function () {
    $assignment = feeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Excursion Fee')->id,
        'amount' => '25000',
    ]);

    generateBills();

    $bill = billFor($this->fx['john']->id);
    $item = $bill->items()->first();

    $payment = Payment::create([
        'school_id' => $this->fx['school']->id,
        'student_id' => $this->fx['john']->id,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'student_bill_id' => $bill->id,
        'reference' => 'CYF-00001',
        'amount' => '25000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'source' => Payment::SOURCE_ADMIN_MANUAL,
        'status' => Payment::STATUS_VERIFIED,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'student_bill_item_id' => $item->id,
        'amount' => '25000',
    ]);

    $this->assignments->update($assignment, ['is_active' => false], $this->fx['admin']);
    generateBills();

    // Deleting it would orphan the allocation and silently change what John
    // paid for.
    expect(StudentBillItem::withoutGlobalScopes()->find($item->id))->not->toBeNull()
        ->and($item->fresh()->is_removed)->toBeTrue()
        ->and($bill->activeItems()->sum('net_amount'))->toEqual('0.00');
});

it('revives a retired line if the fee is assigned again', function () {
    $assignment = feeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Excursion Fee')->id,
        'amount' => '25000',
    ]);

    generateBills();
    $item = billFor($this->fx['john']->id)->items()->first();
    $item->update(['is_removed' => true, 'removed_reason' => 'withdrawn']);

    generateBills();

    expect($item->fresh()->is_removed)->toBeFalse()
        ->and($item->fresh()->removed_reason)->toBeNull();
});

it('snapshots the class the student sat in', function () {
    feeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Development Levy')->id,
        'amount' => '15000',
    ]);

    generateBills();

    $bill = billFor($this->fx['john']->id);
    expect($bill->school_class_id)->toBe($this->fx['jss2']->id)
        ->and($bill->class_arm_id)->toBe($this->fx['armA']->id);
});

it('bills only the students an assignment reaches when syncing it', function () {
    $assignment = feeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS_ARM,
        'class_arm_id' => $this->fx['armA']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Practical Fee')->id,
        'amount' => '5000',
    ]);

    $summary = $this->bills->syncAssignment($assignment, $this->fx['admin']);

    expect($summary['students'])->toBe(1)
        ->and(StudentBill::count())->toBe(1)
        ->and(billFor($this->fx['john']->id))->not->toBeNull()
        ->and(billFor($this->fx['mary']->id))->toBeNull();
});

it('does not bill withdrawn students', function () {
    $this->fx['david']->update(['status' => 'graduated']);

    feeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Development Levy')->id,
        'amount' => '15000',
    ]);

    generateBills();

    expect(StudentBill::count())->toBe(2)
        ->and(billFor($this->fx['david']->id))->toBeNull();
});
