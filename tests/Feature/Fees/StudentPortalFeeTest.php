<?php

use App\Models\BankDetail;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\StudentBill;
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

beforeEach(function () {
    $this->fx = FeesFixture::make();

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

    Sanctum::actingAs($this->fx['john'], [], 'student');
});

it('shows the student their current bill with the four figures that matter', function () {
    getJson('/api/v1/student/fees/bill')
        ->assertOk()
        ->assertJsonPath('data.totals.total', '110000.00')
        ->assertJsonPath('data.totals.verified_paid', '0.00')
        ->assertJsonPath('data.totals.pending', '0.00')
        ->assertJsonPath('data.totals.outstanding', '110000.00')
        ->assertJsonPath('data.items.0.name', 'Tuition');
});

it('returns an empty bill rather than an error for a term with no fees yet', function () {
    StudentBill::where('student_id', $this->fx['john']->id)->delete();

    getJson('/api/v1/student/fees/bill')
        ->assertOk()
        ->assertJsonPath('data', null)
        ->assertJsonPath('totals.total', '0.00')
        ->assertJsonPath('totals.status', BillCalculator::STATUS_UNPAID);
});

it('lists the student bill history', function () {
    getJson('/api/v1/student/fees/bills')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.totals.total', '110000.00');
});

it('refuses another student bill', function () {
    $marysBill = StudentBill::where('student_id', $this->fx['mary']->id)->firstOrFail();

    getJson("/api/v1/student/fees/bills/{$marysBill->id}")->assertNotFound();
});

it('shows where to pay, with a reference the school can trace', function () {
    BankDetail::create([
        'school_id' => $this->fx['school']->id,
        'bank_name' => 'First Bank',
        'account_name' => 'Example School Ltd',
        'account_number' => '1234567890',
        'is_default' => true,
        'is_active' => true,
    ]);

    BankDetail::create([
        'school_id' => $this->fx['school']->id,
        'bank_name' => 'Closed Bank',
        'account_name' => 'Old Account',
        'account_number' => '999',
        'is_active' => false,
    ]);

    $response = getJson('/api/v1/student/fees/payment-accounts')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.bank_name'))->toBe('First Bank')
        ->and($response->json('payment_reference'))
        ->toBe('John Doe / '.$this->fx['john']->admission_no);
});

it('accepts submitted evidence without touching the balance', function () {
    Storage::fake('evidence');
    Queue::fake();

    $response = postJson('/api/v1/student/fees/payments', [
        'amount' => '50000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'payer_reference' => 'FBN/2026/99812',
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', Payment::STATUS_PENDING)
        ->assertJsonCount(1, 'data.evidence');

    expect($response->json('data.reference'))->toStartWith('CYF-');

    // The heart of the workflow: evidence is not money.
    getJson('/api/v1/student/fees/bill')
        ->assertOk()
        ->assertJsonPath('data.totals.pending', '50000.00')
        ->assertJsonPath('data.totals.verified_paid', '0.00')
        ->assertJsonPath('data.totals.outstanding', '110000.00')
        ->assertJsonPath('data.totals.status', BillCalculator::STATUS_PENDING);
});

it('keeps evidence off the public disk', function () {
    Storage::fake('evidence');
    Storage::fake('public');

    postJson('/api/v1/student/fees/payments', [
        'amount' => '50000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->assertCreated();

    $evidence = \App\Models\PaymentEvidence::firstOrFail();

    expect($evidence->disk)->toBe('evidence')
        ->and(Storage::disk('evidence')->exists($evidence->path))->toBeTrue()
        ->and(Storage::disk('public')->exists($evidence->path))->toBeFalse();
});

it('never exposes the stored path of an evidence file', function () {
    Storage::fake('evidence');

    $response = postJson('/api/v1/student/fees/payments', [
        'amount' => '5000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->assertCreated();

    expect($response->json('data.evidence.0'))->not->toHaveKey('path')
        ->and($response->json('data.evidence.0'))->not->toHaveKey('disk');
});

it('rejects an executable disguised as evidence', function () {
    Storage::fake('evidence');

    postJson('/api/v1/student/fees/payments', [
        'amount' => '50000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'evidence' => [UploadedFile::fake()->create('payload.php', 10, 'application/x-php')],
    ])->assertStatus(422);

    expect(Payment::count())->toBe(0);
});

it('rejects a payment dated in the future', function () {
    Storage::fake('evidence');

    postJson('/api/v1/student/fees/payments', [
        'amount' => '50000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->addDay()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->assertStatus(422)->assertJsonValidationErrors('paid_at');
});

it('requires evidence', function () {
    postJson('/api/v1/student/fees/payments', [
        'amount' => '50000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
    ])->assertStatus(422)->assertJsonValidationErrors('evidence');
});

it('refuses a term belonging to another school', function () {
    Storage::fake('evidence');
    $other = FeesFixture::make();

    postJson('/api/v1/student/fees/payments', [
        'amount' => '50000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $other['session']->id,
        'term_id' => $other['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->assertStatus(422);

    expect(Payment::count())->toBe(0);
});

it('shows the student their own payment history only', function () {
    Payment::create([
        'school_id' => $this->fx['school']->id,
        'student_id' => $this->fx['mary']->id,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'reference' => 'CYF-99999',
        'amount' => '1000',
        'method' => Payment::METHOD_CASH,
        'paid_at' => now()->toDateString(),
        'source' => Payment::SOURCE_ADMIN_MANUAL,
        'status' => Payment::STATUS_VERIFIED,
    ]);

    getJson('/api/v1/student/fees/payments')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('requires a logged-in student', function () {
    app('auth')->forgetGuards();

    getJson('/api/v1/student/fees/bill')->assertUnauthorized();
});

it('shows the student full detail on one of their own payments, including what it paid for', function () {
    Storage::fake('evidence');
    Queue::fake();

    $paymentId = postJson('/api/v1/student/fees/payments', [
        'amount' => '50000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->assertCreated()->json('data.id');

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->fx['admin'], [], 'sanctum');
    postJson("/api/v1/fees/payments/{$paymentId}/approve")->assertOk();

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->fx['john'], [], 'student');

    $response = getJson("/api/v1/student/fees/payments/{$paymentId}")->assertOk();

    expect($response->json('data.id'))->toBe($paymentId)
        ->and($response->json('data.status'))->toBe(Payment::STATUS_VERIFIED)
        ->and($response->json('data.allocations'))->not->toBeEmpty()
        // Own-money detail, not admin detail: no reviewer identity leaks in.
        ->and($response->json('data'))->not->toHaveKey('verified_by')
        ->and($response->json('data'))->not->toHaveKey('submitted_by_type');
});

it('refuses to show another student payment detail', function () {
    Storage::fake('evidence');
    Queue::fake();

    $paymentId = postJson('/api/v1/student/fees/payments', [
        'amount' => '50000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->assertCreated()->json('data.id');

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->fx['mary'], [], 'student');

    getJson("/api/v1/student/fees/payments/{$paymentId}")->assertNotFound();
});
