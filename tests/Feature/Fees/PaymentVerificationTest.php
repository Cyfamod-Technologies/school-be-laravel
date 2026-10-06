<?php

use App\Jobs\SendPaymentStatusNotification;
use App\Models\FeeStructure;
use App\Models\FinanceAuditLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentEvidence;
use App\Models\StudentBill;
use App\Models\StudentBillItem;
use App\Services\Fees\BillCalculator;
use App\Services\Fees\BillGenerationService;
use App\Services\Fees\FeeAssignmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FeesFixture;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->fx = FeesFixture::make();
    $assignments = app(FeeAssignmentService::class);

    // Two fees so allocation has somewhere to land: 80,000 + 30,000.
    foreach ([['Tuition', '80000'], ['Development Levy', '30000']] as [$name, $amount]) {
        $assignments->create($this->fx['school'], [
            'scope' => FeeStructure::SCOPE_CLASS,
            'school_class_id' => $this->fx['jss2']->id,
            'session_id' => $this->fx['session']->id,
            'term_id' => $this->fx['term']->id,
            'fee_item_id' => FeesFixture::feeItem($this->fx['school'], $name)->id,
            'amount' => $amount,
        ], $this->fx['admin']);
    }

    app(BillGenerationService::class)->generateForSchool(
        $this->fx['school']->id,
        $this->fx['session']->id,
        $this->fx['term']->id,
    );

    $this->bill = StudentBill::where('student_id', $this->fx['john']->id)->firstOrFail();
    $this->calculator = app(BillCalculator::class);

    Storage::fake('evidence');
    Queue::fake();
});

function submitAsStudent(string $amount = '50000'): string
{
    Sanctum::actingAs(test()->fx['john'], [], 'student');

    $id = postJson('/api/v1/student/fees/payments', [
        'amount' => $amount,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => test()->fx['session']->id,
        'term_id' => test()->fx['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->assertCreated()->json('data.id');

    app('auth')->forgetGuards();
    Sanctum::actingAs(test()->fx['admin'], [], 'sanctum');

    return $id;
}

it('puts a submission in the queue for review', function () {
    submitAsStudent();

    getJson('/api/v1/fees/payments?status='.Payment::STATUS_PENDING)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student.name', 'John Doe')
        ->assertJsonPath('data.0.amount', '50000.00')
        ->assertJsonCount(1, 'data.0.evidence');
});

it('verifies a payment, issues a receipt and allocates it oldest first', function () {
    $id = submitAsStudent('90000');

    $response = postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();

    expect($response->json('data.status'))->toBe(Payment::STATUS_VERIFIED)
        ->and($response->json('data.receipt_number'))->toStartWith('RCT-')
        ->and($response->json('data.verified_at'))->not->toBeNull();

    // 90,000 covers Tuition in full and 10,000 of the levy.
    $paid = $this->calculator->paidPerItem($this->bill->id);
    $tuition = StudentBillItem::where('name', 'Tuition')->where('student_bill_id', $this->bill->id)->firstOrFail();
    $levy = StudentBillItem::where('name', 'Development Levy')->where('student_bill_id', $this->bill->id)->firstOrFail();

    expect($paid[$tuition->id])->toBe('80000.00')
        ->and($paid[$levy->id])->toBe('10000.00');

    $totals = $this->calculator->forBill($this->bill);
    expect($totals['verified_paid'])->toBe('90000.00')
        ->and($totals['outstanding'])->toBe('20000.00')
        ->and($totals['status'])->toBe(BillCalculator::STATUS_PARTIALLY_PAID);
});

it('records who verified it and when, in the audit trail', function () {
    $id = submitAsStudent();

    postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();

    $log = FinanceAuditLog::where('action', 'payment.verified')->firstOrFail();

    expect($log->actor_id)->toBe($this->fx['admin']->id)
        ->and($log->subject_id)->toBe($id)
        ->and($log->before['status'])->toBe(Payment::STATUS_PENDING)
        ->and($log->after['status'])->toBe(Payment::STATUS_VERIFIED);
});

it('tells the student their payment was verified', function () {
    $id = submitAsStudent();

    postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();

    Queue::assertPushed(
        SendPaymentStatusNotification::class,
        fn ($job) => $job->paymentId === $id && $job->status === Payment::STATUS_VERIFIED,
    );
});

it('refuses to verify the same payment twice', function () {
    $id = submitAsStudent();

    postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();
    postJson("/api/v1/fees/payments/{$id}/approve")->assertStatus(422);

    // The crucial part: the student was credited once, not twice.
    expect($this->calculator->forBill($this->bill)['verified_paid'])->toBe('50000.00');
});

it('rejects a payment with a reason the student can read', function () {
    $id = submitAsStudent();

    postJson("/api/v1/fees/payments/{$id}/reject", [
        'reason' => 'The uploaded receipt is for a different account.',
    ])->assertOk()->assertJsonPath('data.status', Payment::STATUS_REJECTED);

    // Kept for audit, not deleted, and the balance never moved.
    expect(Payment::find($id))->not->toBeNull()
        ->and($this->calculator->forBill($this->bill)['outstanding'])->toBe('110000.00');

    Sanctum::actingAs($this->fx['john'], [], 'student');

    getJson('/api/v1/student/fees/payments')
        ->assertOk()
        ->assertJsonPath('data.0.status', Payment::STATUS_REJECTED)
        ->assertJsonPath('data.0.rejection_reason', 'The uploaded receipt is for a different account.');
});

it('will not reject without a reason', function () {
    $id = submitAsStudent();

    postJson("/api/v1/fees/payments/{$id}/reject", ['reason' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');
});

it('will not reject a payment that is already verified', function () {
    $id = submitAsStudent();

    postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();
    postJson("/api/v1/fees/payments/{$id}/reject", ['reason' => 'Changed my mind'])
        ->assertStatus(422);
});

it('reverses a verified payment and undoes its allocations', function () {
    $id = submitAsStudent('90000');

    postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();
    expect(PaymentAllocation::where('payment_id', $id)->count())->toBe(2);

    postJson("/api/v1/fees/payments/{$id}/reverse", [
        'reason' => 'The transfer was recalled by the bank.',
    ])->assertOk()->assertJsonPath('data.status', Payment::STATUS_REVERSED);

    expect(PaymentAllocation::where('payment_id', $id)->count())->toBe(0)
        ->and(Payment::find($id))->not->toBeNull()
        ->and($this->calculator->forBill($this->bill)['outstanding'])->toBe('110000.00');
});

it('will not reverse a payment that was never verified', function () {
    $id = submitAsStudent();

    postJson("/api/v1/fees/payments/{$id}/reverse", ['reason' => 'Nope, not verified'])
        ->assertStatus(422);
});

it('lets an admin re-allocate a verified payment by hand', function () {
    $id = submitAsStudent('50000');
    postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();

    $levy = StudentBillItem::where('name', 'Development Levy')
        ->where('student_bill_id', $this->bill->id)
        ->firstOrFail();

    putJson("/api/v1/fees/payments/{$id}/allocations", [
        'allocations' => [
            ['student_bill_item_id' => $levy->id, 'amount' => '30000'],
        ],
    ])->assertOk();

    $paid = $this->calculator->paidPerItem($this->bill->id);

    expect($paid[$levy->id])->toBe('30000.00')
        // The balance follows the payment, not the allocation: 20,000 of it is
        // now an unallocated credit, and the student is still credited 50,000.
        ->and($this->calculator->forBill($this->bill)['verified_paid'])->toBe('50000.00')
        ->and(Payment::find($id)->unallocatedAmount())->toBe('20000.00');
});

it('refuses to allocate more than a fee is owed', function () {
    $id = submitAsStudent('90000');
    postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();

    $levy = StudentBillItem::where('name', 'Development Levy')
        ->where('student_bill_id', $this->bill->id)
        ->firstOrFail();

    putJson("/api/v1/fees/payments/{$id}/allocations", [
        'allocations' => [
            ['student_bill_item_id' => $levy->id, 'amount' => '50000'],
        ],
    ])->assertStatus(422);
});

it('refuses to allocate more than the payment is worth', function () {
    $id = submitAsStudent('50000');
    postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();

    $items = StudentBillItem::where('student_bill_id', $this->bill->id)->get();

    putJson("/api/v1/fees/payments/{$id}/allocations", [
        'allocations' => $items->map(fn ($item) => [
            'student_bill_item_id' => $item->id,
            'amount' => '30000',
        ])->all(),
    ])->assertStatus(422);
});

it('refuses to allocate another student fees', function () {
    $id = submitAsStudent('50000');
    postJson("/api/v1/fees/payments/{$id}/approve")->assertOk();

    $marysItem = StudentBillItem::whereHas(
        'bill',
        fn ($bill) => $bill->where('student_id', $this->fx['mary']->id)
    )->firstOrFail();

    putJson("/api/v1/fees/payments/{$id}/allocations", [
        'allocations' => [
            ['student_bill_item_id' => $marysItem->id, 'amount' => '1000'],
        ],
    ])->assertStatus(422);
});

it('will not allocate a payment that is not verified', function () {
    $id = submitAsStudent();

    $item = StudentBillItem::where('student_bill_id', $this->bill->id)->firstOrFail();

    putJson("/api/v1/fees/payments/{$id}/allocations", [
        'allocations' => [['student_bill_item_id' => $item->id, 'amount' => '1000']],
    ])->assertStatus(422);
});

it('records a payment taken at the desk as pending, not verified', function () {
    Sanctum::actingAs($this->fx['admin'], [], 'sanctum');

    $response = postJson('/api/v1/fees/payments', [
        'student_id' => $this->fx['john']->id,
        'amount' => '20000',
        'method' => Payment::METHOD_CASH,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
    ])->assertCreated();

    // Recording is not verifying: entering a payment must not be a way to
    // credit a student without the verify permission.
    expect($response->json('data.status'))->toBe(Payment::STATUS_PENDING)
        ->and($this->calculator->forBill($this->bill)['verified_paid'])->toBe('0.00');
});

it('streams evidence only to the school that owns it', function () {
    $id = submitAsStudent();
    $evidence = PaymentEvidence::where('payment_id', $id)->firstOrFail();

    getJson("/api/v1/fees/payments/{$id}/evidence/{$evidence->id}")->assertOk();

    // An admin at another school must not be able to read it.
    $other = FeesFixture::make();
    app('auth')->forgetGuards();
    Sanctum::actingAs($other['admin'], [], 'sanctum');

    getJson("/api/v1/fees/payments/{$id}/evidence/{$evidence->id}")->assertNotFound();
});

it('hides another school payments from the queue', function () {
    submitAsStudent();

    $other = FeesFixture::make();
    app('auth')->forgetGuards();
    Sanctum::actingAs($other['admin'], [], 'sanctum');

    getJson('/api/v1/fees/payments')->assertOk()->assertJsonCount(0, 'data');
});

it('separates verifying from viewing when enforcement is on', function () {
    $id = submitAsStudent();

    config(['features.enforce_endpoint_permissions' => true]);

    $bursar = \App\Models\User::factory()->create([
        'school_id' => $this->fx['school']->id,
        'role' => 'staff',
        'status' => 'active',
    ]);

    app('auth')->forgetGuards();
    Sanctum::actingAs($bursar, [], 'sanctum');

    postJson("/api/v1/fees/payments/{$id}/approve")->assertForbidden();
});

it('gives each school its own reference sequence', function () {
    submitAsStudent();
    $first = Payment::where('school_id', $this->fx['school']->id)->value('reference');

    $other = FeesFixture::make();
    app('auth')->forgetGuards();
    Sanctum::actingAs($other['john'], [], 'student');

    postJson('/api/v1/student/fees/payments', [
        'amount' => '1000',
        'method' => Payment::METHOD_CASH,
        'paid_at' => now()->toDateString(),
        'session_id' => $other['session']->id,
        'term_id' => $other['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->assertCreated();

    $second = Payment::where('school_id', $other['school']->id)->value('reference');

    // Same number on purpose -- references are unique per school, not globally.
    expect($first)->toBe($second);
});
