<?php

use App\Models\FeeStructure;
use App\Models\StudentBill;
use App\Models\StudentBillItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FeesFixture;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->fx = FeesFixture::make();
    Sanctum::actingAs($this->fx['admin'], [], 'sanctum');

    $this->tuition = FeesFixture::feeItem($this->fx['school'], 'Tuition');
    $this->levy = FeesFixture::feeItem($this->fx['school'], 'Development Levy');
});

function assignmentPayload(array $overrides = []): array
{
    return array_merge([
        'scope' => FeeStructure::SCOPE_CLASS,
        'school_class_id' => test()->fx['jss2']->id,
        'session_id' => test()->fx['session']->id,
        'term_id' => test()->fx['term']->id,
        'fee_item_id' => test()->tuition->id,
        'amount' => '80000',
    ], $overrides);
}

it('creates an assignment and bills the students it reaches in the same request', function () {
    $response = postJson('/api/v1/fees/assignments', assignmentPayload());

    $response->assertCreated()
        ->assertJsonPath('data.scope', FeeStructure::SCOPE_CLASS)
        ->assertJsonPath('data.amount', '80000.00')
        ->assertJsonPath('data.scope_label', 'JSS 2')
        ->assertJsonPath('meta.bill_sync.students', 2);

    // John and Mary are in JSS 2; David is in JSS 3.
    expect(StudentBill::count())->toBe(2)
        ->and(StudentBillItem::sum('net_amount'))->toEqual('160000.00');
});

it('creates a student-specific assignment from a list of students', function () {
    $response = postJson('/api/v1/fees/assignments', assignmentPayload([
        'scope' => FeeStructure::SCOPE_STUDENT,
        'school_class_id' => null,
        'fee_item_id' => $this->levy->id,
        'amount' => '25000',
        'student_ids' => [$this->fx['john']->id, $this->fx['david']->id],
    ]));

    $response->assertCreated()
        ->assertJsonPath('meta.bill_sync.students', 2)
        ->assertJsonCount(2, 'data.students');

    expect(StudentBill::count())->toBe(2)
        ->and(StudentBill::where('student_id', $this->fx['mary']->id)->exists())->toBeFalse();
});

it('refuses a duplicate assignment with a validation error', function () {
    postJson('/api/v1/fees/assignments', assignmentPayload())->assertCreated();

    postJson('/api/v1/fees/assignments', assignmentPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('fee_item_id');
});

it('rejects an unknown scope', function () {
    postJson('/api/v1/fees/assignments', assignmentPayload(['scope' => 'everyone']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('scope');
});

it('lists assignments with their student counts', function () {
    postJson('/api/v1/fees/assignments', assignmentPayload())->assertCreated();
    postJson('/api/v1/fees/assignments', assignmentPayload([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'school_class_id' => null,
        'fee_item_id' => $this->levy->id,
        'amount' => '15000',
    ]))->assertCreated();

    $rows = collect(getJson('/api/v1/fees/assignments')->assertOk()->json('data'));

    expect($rows)->toHaveCount(2)
        ->and($rows->firstWhere('scope', FeeStructure::SCOPE_SCHOOL)['scope_label'])->toBe('All Students')
        ->and($rows->firstWhere('scope', FeeStructure::SCOPE_CLASS)['scope_label'])->toBe('JSS 2');
});

it('filters assignments by scope', function () {
    postJson('/api/v1/fees/assignments', assignmentPayload())->assertCreated();
    postJson('/api/v1/fees/assignments', assignmentPayload([
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'school_class_id' => null,
        'fee_item_id' => $this->levy->id,
    ]))->assertCreated();

    getJson('/api/v1/fees/assignments?scope='.FeeStructure::SCOPE_SCHOOL)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('costs an assignment without creating it', function () {
    postJson('/api/v1/fees/assignments/preview', assignmentPayload())
        ->assertOk()
        ->assertJsonPath('data.student_count', 2)
        ->assertJsonPath('data.total_amount', '160000.00');

    expect(FeeStructure::count())->toBe(0);
});

it('re-bills students when the amount changes', function () {
    $id = postJson('/api/v1/fees/assignments', assignmentPayload())->json('data.id');

    putJson("/api/v1/fees/assignments/{$id}", ['amount' => '90000'])
        ->assertOk()
        ->assertJsonPath('data.amount', '90000.00');

    expect(StudentBillItem::sum('net_amount'))->toEqual('180000.00');
});

it('strips the fee from bills when an assignment is deactivated', function () {
    $id = postJson('/api/v1/fees/assignments', assignmentPayload())->json('data.id');

    putJson("/api/v1/fees/assignments/{$id}", ['is_active' => false])->assertOk();

    expect(StudentBillItem::count())->toBe(0);
});

it('re-bills students dropped from a student-specific assignment', function () {
    $id = postJson('/api/v1/fees/assignments', assignmentPayload([
        'scope' => FeeStructure::SCOPE_STUDENT,
        'school_class_id' => null,
        'fee_item_id' => $this->levy->id,
        'amount' => '25000',
        'student_ids' => [$this->fx['john']->id, $this->fx['mary']->id],
    ]))->json('data.id');

    expect(StudentBillItem::count())->toBe(2);

    // Mary is dropped: her line has to go, which means her bill is re-synced
    // even though she is no longer part of the assignment.
    putJson("/api/v1/fees/assignments/{$id}/students", [
        'student_ids' => [$this->fx['john']->id],
    ])->assertOk();

    expect(StudentBillItem::count())->toBe(1)
        ->and(StudentBillItem::first()->student_bill_id)
        ->toBe(StudentBill::where('student_id', $this->fx['john']->id)->value('id'));
});

it('refuses a student list on a non-student assignment', function () {
    $id = postJson('/api/v1/fees/assignments', assignmentPayload())->json('data.id');

    putJson("/api/v1/fees/assignments/{$id}/students", [
        'student_ids' => [$this->fx['john']->id],
    ])->assertStatus(422);
});

it('removes the fee from bills when the assignment is deleted', function () {
    $id = postJson('/api/v1/fees/assignments', assignmentPayload())->json('data.id');

    deleteJson("/api/v1/fees/assignments/{$id}")
        ->assertOk()
        ->assertJsonPath('meta.bill_items_removed', 2);

    expect(FeeStructure::count())->toBe(0)
        ->and(StudentBillItem::count())->toBe(0);
});

it('hides another school assignments', function () {
    $other = FeesFixture::make();
    $otherItem = FeesFixture::feeItem($other['school'], 'Tuition');

    $foreign = FeeStructure::create([
        'school_id' => $other['school']->id,
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'session_id' => $other['session']->id,
        'term_id' => $other['term']->id,
        'fee_item_id' => $otherItem->id,
        'amount' => '1000',
    ]);

    getJson("/api/v1/fees/assignments/{$foreign->id}")->assertNotFound();
    putJson("/api/v1/fees/assignments/{$foreign->id}", ['amount' => '2'])->assertNotFound();
    deleteJson("/api/v1/fees/assignments/{$foreign->id}")->assertNotFound();

    getJson('/api/v1/fees/assignments')->assertOk()->assertJsonCount(0, 'data');
});

it('requires authentication', function () {
    app('auth')->forgetGuards();

    getJson('/api/v1/fees/assignments')->assertUnauthorized();
});

it('refuses a user without the finance permission when enforcement is on', function () {
    config(['features.enforce_endpoint_permissions' => true]);

    $bursar = User::factory()->create([
        'school_id' => $this->fx['school']->id,
        'role' => 'staff',
        'status' => 'active',
    ]);

    Sanctum::actingAs($bursar, [], 'sanctum');

    getJson('/api/v1/fees/assignments')->assertForbidden();
    postJson('/api/v1/fees/assignments', assignmentPayload())->assertForbidden();
});

it('retires rather than deletes a billed line when the assignment is deleted', function () {
    $id = postJson('/api/v1/fees/assignments', assignmentPayload())->json('data.id');

    $item = StudentBillItem::where('fee_structure_id', $id)->firstOrFail();

    $payment = \App\Models\Payment::create([
        'school_id' => $this->fx['school']->id,
        'student_id' => $this->fx['john']->id,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'reference' => 'CYF-'.uniqid(),
        'amount' => '80000',
        'method' => \App\Models\Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'source' => \App\Models\Payment::SOURCE_ADMIN_MANUAL,
        'status' => \App\Models\Payment::STATUS_VERIFIED,
    ]);

    \App\Models\PaymentAllocation::create([
        'payment_id' => $payment->id,
        'student_bill_item_id' => $item->id,
        'amount' => '80000',
    ]);

    deleteJson("/api/v1/fees/assignments/{$id}")->assertOk();

    // The allocation still points at a line that says what it paid for, and
    // the line no longer counts towards the balance.
    expect($item->fresh())->not->toBeNull()
        ->and($item->fresh()->is_removed)->toBeTrue()
        ->and(StudentBillItem::where('is_removed', false)->count())->toBe(0);
});

it('leaves no unattributable lines behind when an assignment is deleted', function () {
    $id = postJson('/api/v1/fees/assignments', assignmentPayload())->json('data.id');

    deleteJson("/api/v1/fees/assignments/{$id}")->assertOk();

    // A nullOnDelete foreign key would otherwise leave these orphaned and
    // invisible to the generation sync -- still payable, and untraceable.
    expect(StudentBillItem::whereNull('fee_structure_id')->count())->toBe(0);
});
