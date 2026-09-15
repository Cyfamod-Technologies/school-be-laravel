<?php

use App\Models\FeeStructure;
use App\Services\Fees\FeeAssignmentService;
use Illuminate\Validation\ValidationException;
use Tests\Support\FeesFixture;

beforeEach(function () {
    $this->fx = FeesFixture::make();
    $this->service = app(FeeAssignmentService::class);
});

function createFeeAssignment(array $data): FeeStructure
{
    return test()->service->create(test()->fx['school'], array_merge([
        'session_id' => test()->fx['session']->id,
        'term_id' => test()->fx['term']->id,
        'amount' => '1000',
    ], $data), test()->fx['admin']);
}

it('reaches every student with a school-wide fee', function () {
    $levy = FeesFixture::feeItem($this->fx['school'], 'Development Levy');

    $assignment = createFeeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => $levy->id,
        'amount' => '15000',
    ]);

    expect($this->service->resolveStudentIds($assignment))
        ->toHaveCount(3)
        ->toContain($this->fx['john']->id, $this->fx['mary']->id, $this->fx['david']->id);
});

it('reaches only the class named by a class fee', function () {
    $tuition = FeesFixture::feeItem($this->fx['school'], 'Tuition');

    $assignment = createFeeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => $this->fx['jss2']->id,
        'fee_item_id' => $tuition->id,
        'amount' => '80000',
    ]);

    // John and Mary are both in JSS 2; David is in JSS 3.
    expect($this->service->resolveStudentIds($assignment))
        ->toHaveCount(2)
        ->toContain($this->fx['john']->id, $this->fx['mary']->id)
        ->not->toContain($this->fx['david']->id);
});

it('reaches only the arm named by a class-arm fee', function () {
    $practical = FeesFixture::feeItem($this->fx['school'], 'Practical Fee');

    $assignment = createFeeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS_ARM,
        'class_arm_id' => $this->fx['armA']->id,
        'fee_item_id' => $practical->id,
        'amount' => '5000',
    ]);

    expect($this->service->resolveStudentIds($assignment))
        ->toBe([$this->fx['john']->id]);
});

it('stamps the parent class on an arm fee so it can still be reported by class', function () {
    $practical = FeesFixture::feeItem($this->fx['school'], 'Practical Fee');

    $assignment = createFeeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS_ARM,
        'class_arm_id' => $this->fx['armA']->id,
        'fee_item_id' => $practical->id,
        'amount' => '5000',
    ]);

    expect($assignment->school_class_id)->toBe($this->fx['jss2']->id);
});

it('reaches only the students named by a student fee', function () {
    $excursion = FeesFixture::feeItem($this->fx['school'], 'Excursion Fee');

    $assignment = createFeeAssignment([
        'scope' => FeeStructure::SCOPE_STUDENT,
        'fee_item_id' => $excursion->id,
        'amount' => '25000',
        'student_ids' => [$this->fx['john']->id, $this->fx['mary']->id],
    ]);

    expect($this->service->resolveStudentIds($assignment))
        ->toHaveCount(2)
        ->toContain($this->fx['john']->id, $this->fx['mary']->id)
        ->not->toContain($this->fx['david']->id);
});

it('clears targets that do not belong to the chosen scope', function () {
    $levy = FeesFixture::feeItem($this->fx['school'], 'Development Levy');

    // A school-wide fee carrying a leftover class from the form would poison
    // the assignment key and let a duplicate through.
    $assignment = createFeeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'school_class_id' => $this->fx['jss2']->id,
        'class_arm_id' => $this->fx['armA']->id,
        'fee_item_id' => $levy->id,
    ]);

    expect($assignment->school_class_id)->toBeNull()
        ->and($assignment->class_arm_id)->toBeNull();
});

it('rejects a second assignment of the same fee to the same scope and period', function () {
    $levy = FeesFixture::feeItem($this->fx['school'], 'Development Levy');

    createFeeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => $levy->id,
    ]);

    expect(fn () => createFeeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => $levy->id,
    ]))->toThrow(ValidationException::class);
});

it('allows the same fee item at different scopes in the same period', function () {
    $levy = FeesFixture::feeItem($this->fx['school'], 'Development Levy');

    createFeeAssignment(['scope' => FeeStructure::SCOPE_SCHOOL, 'fee_item_id' => $levy->id]);
    createFeeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => $this->fx['jss2']->id,
        'fee_item_id' => $levy->id,
    ]);

    expect(FeeStructure::count())->toBe(2);
});

it('allows two student-specific assignments of the same fee item', function () {
    $excursion = FeesFixture::feeItem($this->fx['school'], 'Excursion Fee');

    // Two excursions in one term is a real thing, so student-scoped
    // assignments carry no natural key to clash on.
    createFeeAssignment([
        'scope' => FeeStructure::SCOPE_STUDENT,
        'fee_item_id' => $excursion->id,
        'student_ids' => [$this->fx['john']->id],
    ]);

    createFeeAssignment([
        'scope' => FeeStructure::SCOPE_STUDENT,
        'fee_item_id' => $excursion->id,
        'student_ids' => [$this->fx['mary']->id],
    ]);

    expect(FeeStructure::count())->toBe(2);
});

it('refuses a class that belongs to another school', function () {
    $otherSchool = FeesFixture::make();
    $levy = FeesFixture::feeItem($this->fx['school'], 'Development Levy');

    expect(fn () => createFeeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => $otherSchool['jss2']->id,
        'fee_item_id' => $levy->id,
    ]))->toThrow(ValidationException::class);
});

it('refuses students who belong to another school', function () {
    $otherSchool = FeesFixture::make();
    $excursion = FeesFixture::feeItem($this->fx['school'], 'Excursion Fee');

    expect(fn () => createFeeAssignment([
        'scope' => FeeStructure::SCOPE_STUDENT,
        'fee_item_id' => $excursion->id,
        'student_ids' => [$this->fx['john']->id, $otherSchool['john']->id],
    ]))->toThrow(ValidationException::class);
});

it('refuses a fee item that belongs to another school', function () {
    $otherSchool = FeesFixture::make();
    $foreignItem = FeesFixture::feeItem($otherSchool['school'], 'Development Levy');

    expect(fn () => createFeeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => $foreignItem->id,
    ]))->toThrow(ValidationException::class);
});

it('costs an assignment before it is committed', function () {
    $tuition = FeesFixture::feeItem($this->fx['school'], 'Tuition');

    $preview = $this->service->preview($this->fx['school'], [
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => $this->fx['jss2']->id,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'fee_item_id' => $tuition->id,
        'amount' => '80000',
    ]);

    expect($preview['student_count'])->toBe(2)
        ->and($preview['total_amount'])->toBe('160000.00')
        ->and(FeeStructure::count())->toBe(0);
});

it('leaves withdrawn students out of new assignments', function () {
    $this->fx['mary']->update(['status' => 'withdrawn']);

    $levy = FeesFixture::feeItem($this->fx['school'], 'Development Levy');

    $assignment = createFeeAssignment([
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => $this->fx['jss2']->id,
        'fee_item_id' => $levy->id,
    ]);

    expect($this->service->resolveStudentIds($assignment))->toBe([$this->fx['john']->id]);
});

it('records who created an assignment', function () {
    $levy = FeesFixture::feeItem($this->fx['school'], 'Development Levy');

    $assignment = createFeeAssignment([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'fee_item_id' => $levy->id,
    ]);

    expect($assignment->created_by)->toBe($this->fx['admin']->id)
        ->and(\App\Models\FinanceAuditLog::where('subject_id', $assignment->id)->exists())->toBeTrue();
});
