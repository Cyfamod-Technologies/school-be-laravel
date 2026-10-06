<?php

use App\Models\FeeStructure;
use App\Models\Payment;
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
        'amount' => '80000',
    ], $this->fx['admin']);

    app(BillGenerationService::class)->generateForSchool(
        $this->fx['school']->id,
        $this->fx['session']->id,
        $this->fx['term']->id,
    );

    Storage::fake('evidence');
    Queue::fake();

    Sanctum::actingAs($this->fx['john'], [], 'student');

    $this->paymentId = postJson('/api/v1/student/fees/payments', [
        'amount' => '80000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->json('data.id');

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->fx['admin'], [], 'sanctum');
});

it('will not generate a receipt for an unverified payment', function () {
    getJson("/api/v1/fees/payments/{$this->paymentId}/receipt.pdf")->assertStatus(422);
});

it('generates a PDF receipt once verified', function () {
    postJson("/api/v1/fees/payments/{$this->paymentId}/approve")->assertOk();

    $response = getJson("/api/v1/fees/payments/{$this->paymentId}/receipt.pdf");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('lets the student download their own receipt', function () {
    postJson("/api/v1/fees/payments/{$this->paymentId}/approve")->assertOk();

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->fx['john'], [], 'student');

    getJson("/api/v1/student/fees/payments/{$this->paymentId}/receipt.pdf")
        ->assertOk();
});

it('refuses a receipt for another student payment', function () {
    postJson("/api/v1/fees/payments/{$this->paymentId}/approve")->assertOk();

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->fx['mary'], [], 'student');

    getJson("/api/v1/student/fees/payments/{$this->paymentId}/receipt.pdf")
        ->assertNotFound();
});

it('hides a receipt from another school', function () {
    postJson("/api/v1/fees/payments/{$this->paymentId}/approve")->assertOk();

    $other = FeesFixture::make();
    app('auth')->forgetGuards();
    Sanctum::actingAs($other['admin'], [], 'sanctum');

    getJson("/api/v1/fees/payments/{$this->paymentId}/receipt.pdf")->assertNotFound();
});
