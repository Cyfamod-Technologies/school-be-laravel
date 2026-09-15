<?php

use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\StudentBill;
use App\Services\Fees\BillCalculator;
use App\Services\Fees\BillGenerationService;
use App\Services\Fees\FeeAssignmentService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FeesFixture;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->fx = FeesFixture::make();
    Sanctum::actingAs($this->fx['admin'], [], 'sanctum');

    app(FeeAssignmentService::class)->create($this->fx['school'], [
        'scope' => FeeStructure::SCOPE_SCHOOL,
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

function verifiedPayment(string $amount, string $status = Payment::STATUS_VERIFIED): Payment
{
    return Payment::create([
        'school_id' => test()->fx['school']->id,
        'student_id' => test()->fx['john']->id,
        'session_id' => test()->fx['session']->id,
        'term_id' => test()->fx['term']->id,
        'student_bill_id' => test()->bill->id,
        'reference' => 'CYF-'.uniqid(),
        'amount' => $amount,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'source' => Payment::SOURCE_ADMIN_MANUAL,
        'status' => $status,
    ]);
}

it('lists bills with their balances', function () {
    verifiedPayment('50000');

    $response = getJson('/api/v1/fees/bills?session_id='.$this->fx['session']->id.'&term_id='.$this->fx['term']->id);

    $response->assertOk();

    $john = collect($response->json('data'))
        ->firstWhere('student_id', $this->fx['john']->id);

    expect($john['totals']['total'])->toBe('110000.00')
        ->and($john['totals']['verified_paid'])->toBe('50000.00')
        ->and($john['totals']['outstanding'])->toBe('60000.00')
        ->and($john['totals']['status'])->toBe(BillCalculator::STATUS_PARTIALLY_PAID);
});

it('filters bills by a derived payment status', function () {
    verifiedPayment('110000');

    getJson('/api/v1/fees/bills?status='.BillCalculator::STATUS_PAID)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_id', $this->fx['john']->id);

    getJson('/api/v1/fees/bills?status='.BillCalculator::STATUS_UNPAID)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters bills by class arm', function () {
    getJson('/api/v1/fees/bills?class_arm_id='.$this->fx['armA']->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_id', $this->fx['john']->id);
});

it('finds a bill by student name or admission number', function () {
    getJson('/api/v1/fees/bills?search=Mary')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_id', $this->fx['mary']->id);
});

it('shows a bill with its line items and what each has been paid', function () {
    $response = getJson("/api/v1/fees/bills/{$this->bill->id}");

    $response->assertOk()
        ->assertJsonPath('data.totals.total', '110000.00')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.name', 'Tuition')
        ->assertJsonPath('data.items.0.paid_amount', '0.00')
        ->assertJsonPath('data.items.0.source', FeeStructure::SCOPE_SCHOOL);
});

it('keeps a pending payment out of the balance but visible', function () {
    verifiedPayment('20000', Payment::STATUS_PENDING);

    getJson("/api/v1/fees/bills/{$this->bill->id}")
        ->assertOk()
        ->assertJsonPath('data.totals.pending', '20000.00')
        ->assertJsonPath('data.totals.outstanding', '110000.00')
        ->assertJsonPath('data.totals.status', BillCalculator::STATUS_PENDING);
});

it('fetches a bill by student for the school current period', function () {
    getJson("/api/v1/fees/students/{$this->fx['john']->id}/bill")
        ->assertOk()
        ->assertJsonPath('data.id', $this->bill->id);
});

it('says so when a student has no bill for the period', function () {
    $this->bill->delete();

    getJson("/api/v1/fees/students/{$this->fx['john']->id}/bill")->assertNotFound();
});

it('regenerates bills on demand and stays idempotent', function () {
    postJson('/api/v1/fees/bills/generate', [
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.students', 3)
        ->assertJsonPath('data.items_created', 0);

    expect(StudentBill::count())->toBe(3);
});

it('hides another school bills', function () {
    $other = FeesFixture::make();

    $foreignBill = StudentBill::create([
        'school_id' => $other['school']->id,
        'student_id' => $other['john']->id,
        'session_id' => $other['session']->id,
        'term_id' => $other['term']->id,
    ]);

    getJson("/api/v1/fees/bills/{$foreignBill->id}")->assertNotFound();
    getJson("/api/v1/fees/students/{$other['john']->id}/bill")->assertNotFound();
    getJson('/api/v1/fees/bills')->assertOk()->assertJsonCount(3, 'data');
});

it('requires the finance permission when enforcement is on', function () {
    config(['features.enforce_endpoint_permissions' => true]);

    $bursar = \App\Models\User::factory()->create([
        'school_id' => $this->fx['school']->id,
        'role' => 'staff',
        'status' => 'active',
    ]);

    Sanctum::actingAs($bursar, [], 'sanctum');

    getJson('/api/v1/fees/bills')->assertForbidden();
    postJson('/api/v1/fees/bills/generate', [
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
    ])->assertForbidden();
});
