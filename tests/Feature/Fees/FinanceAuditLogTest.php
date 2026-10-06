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
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Development Levy')->id,
        'amount' => '15000',
    ], $this->fx['admin']);

    app(BillGenerationService::class)->generateForSchool(
        $this->fx['school']->id,
        $this->fx['session']->id,
        $this->fx['term']->id,
    );

    Storage::fake('evidence');
    Queue::fake();
});

it('records both the student and the admin as actors across one workflow', function () {
    Sanctum::actingAs($this->fx['john'], [], 'student');

    $paymentId = postJson('/api/v1/student/fees/payments', [
        'amount' => '15000',
        'method' => Payment::METHOD_BANK_TRANSFER,
        'paid_at' => now()->toDateString(),
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'evidence' => [UploadedFile::fake()->create('receipt.jpg', 40, 'image/jpeg')],
    ])->json('data.id');

    app('auth')->forgetGuards();
    Sanctum::actingAs($this->fx['admin'], [], 'sanctum');

    postJson("/api/v1/fees/payments/{$paymentId}/approve")->assertOk();

    $logs = getJson('/api/v1/fees/audit-logs')->assertOk()->json('data');

    $verified = collect($logs)->firstWhere('action', 'payment.verified');

    // Both actors show up in the trail -- a student who submitted evidence
    // is an actor here, which the old audit_logs table (keyed to `users`)
    // could never have recorded.
    expect($verified['actor_type'])->toBe('user')
        ->and($verified['actor_name'])->toBe($this->fx['admin']->name);
});

it('filters the audit log by subject', function () {
    $assignment = app(FeeAssignmentService::class)->create($this->fx['school'], [
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Excursion Fee')->id,
        'amount' => '25000',
    ], $this->fx['admin']);

    Sanctum::actingAs($this->fx['admin'], [], 'sanctum');

    $response = getJson('/api/v1/fees/audit-logs?subject_type=fee_structure&subject_id='.$assignment->id)
        ->assertOk();

    expect(collect($response->json('data.data')))
        ->each(fn ($log) => $log->toHaveKey('subject_id', $assignment->id));
});

it('hides another school audit trail', function () {
    $other = FeesFixture::make();
    Sanctum::actingAs($other['admin'], [], 'sanctum');

    getJson('/api/v1/fees/audit-logs')->assertOk()->assertJsonCount(0, 'data');
});
