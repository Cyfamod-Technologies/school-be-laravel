<?php

use App\Models\FeeAdjustment;
use App\Models\FeeStructure;
use App\Models\StudentBillItem;
use App\Services\Fees\BillGenerationService;
use App\Services\Fees\FeeAssignmentService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FeesFixture;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fx = FeesFixture::make();

    app(FeeAssignmentService::class)->create($this->fx['school'], [
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => $this->fx['jss2']->id,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Tuition')->id,
        'amount' => '80000',
    ], $this->fx['admin']);

    app(BillGenerationService::class)->generateForSchool(
        $this->fx['school']->id,
        $this->fx['session']->id,
        $this->fx['term']->id,
    );

    $this->item = StudentBillItem::whereHas(
        'bill',
        fn ($bill) => $bill->where('student_id', $this->fx['john']->id)
    )->firstOrFail();

    Sanctum::actingAs($this->fx['admin'], [], 'sanctum');
});

it('applies a discount and reduces what is payable', function () {
    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'discount',
        'amount' => '10000',
        'reason' => 'Sibling discount',
    ])
        ->assertCreated()
        ->assertJsonPath('data.bill_item.net_amount', '70000.00');
});

it('applies a surcharge and increases what is payable', function () {
    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'surcharge',
        'amount' => '5000',
        'reason' => 'Late payment penalty',
    ])
        ->assertCreated()
        ->assertJsonPath('data.bill_item.net_amount', '85000.00');
});

it('waives whatever is currently owed on the line, not the original amount', function () {
    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'discount',
        'amount' => '20000',
        'reason' => 'Partial scholarship',
    ])->assertCreated();

    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'waiver',
        'reason' => 'Full scholarship approved',
    ])
        ->assertCreated()
        ->assertJsonPath('data.amount', '60000.00')
        ->assertJsonPath('data.bill_item.net_amount', '0.00');
});

it('refuses a waiver when nothing is left on the line', function () {
    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'waiver',
        'reason' => 'First waiver',
    ])->assertCreated();

    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'waiver',
        'reason' => 'Second waiver',
    ])->assertStatus(422);
});

it('requires an amount for a discount but not for a waiver', function () {
    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'discount',
        'reason' => 'No amount given',
    ])->assertStatus(422)->assertJsonValidationErrors('amount');
});

it('requires a reason', function () {
    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'discount',
        'amount' => '5000',
        'reason' => '',
    ])->assertStatus(422)->assertJsonValidationErrors('reason');
});

it('leaves an audit record for every adjustment', function () {
    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'discount',
        'amount' => '10000',
        'reason' => 'Sibling discount',
    ])->assertCreated();

    expect(\App\Models\FinanceAuditLog::where('action', 'fee_adjustment.applied')->count())->toBe(1);
});

it('reverses an adjustment and restores the line', function () {
    $adjustmentId = postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'discount',
        'amount' => '10000',
        'reason' => 'Sibling discount',
    ])->json('data.adjustment_id');

    deleteJson("/api/v1/fees/adjustments/{$adjustmentId}")->assertOk();

    expect($this->item->fresh()->net_amount)->toEqual('80000.00')
        ->and(FeeAdjustment::find($adjustmentId)->reversed_at)->not->toBeNull();
});

it('will not reverse the same adjustment twice', function () {
    $adjustmentId = postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'discount',
        'amount' => '10000',
        'reason' => 'Sibling discount',
    ])->json('data.adjustment_id');

    deleteJson("/api/v1/fees/adjustments/{$adjustmentId}")->assertOk();
    deleteJson("/api/v1/fees/adjustments/{$adjustmentId}")->assertStatus(422);
});

it('refuses to adjust a fee that has been withdrawn from the bill', function () {
    $this->item->update(['is_removed' => true, 'removed_reason' => 'test']);

    postJson("/api/v1/fees/bill-items/{$this->item->id}/adjustments", [
        'type' => 'discount',
        'amount' => '5000',
        'reason' => 'Too late',
    ])->assertStatus(422);
});

it('hides another school bill items', function () {
    $other = FeesFixture::make();
    app(FeeAssignmentService::class)->create($other['school'], [
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'session_id' => $other['session']->id,
        'term_id' => $other['term']->id,
        'fee_item_id' => FeesFixture::feeItem($other['school'], 'Fee')->id,
        'amount' => '1000',
    ], $other['admin']);
    app(BillGenerationService::class)->generateForSchool($other['school']->id, $other['session']->id, $other['term']->id);

    $foreignItem = StudentBillItem::whereHas(
        'bill',
        fn ($bill) => $bill->where('school_id', $other['school']->id)
    )->firstOrFail();

    postJson("/api/v1/fees/bill-items/{$foreignItem->id}/adjustments", [
        'type' => 'discount',
        'amount' => '100',
        'reason' => 'Should not work',
    ])->assertNotFound();
});
