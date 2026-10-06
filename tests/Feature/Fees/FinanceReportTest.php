<?php

use App\Models\FeeStructure;
use App\Models\Payment;
use App\Services\Fees\BillGenerationService;
use App\Services\Fees\FeeAssignmentService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FeesFixture;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->fx = FeesFixture::make();

    // 15,000 school-wide x3 students = 45,000 expected.
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

    Payment::create([
        'school_id' => $this->fx['school']->id,
        'student_id' => $this->fx['john']->id,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'reference' => 'CYF-10001',
        'amount' => '15000',
        'method' => Payment::METHOD_CASH,
        'paid_at' => now()->toDateString(),
        'source' => Payment::SOURCE_ADMIN_MANUAL,
        'status' => Payment::STATUS_VERIFIED,
    ]);

    Payment::create([
        'school_id' => $this->fx['school']->id,
        'student_id' => $this->fx['mary']->id,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'reference' => 'CYF-10002',
        'amount' => '5000',
        'method' => Payment::METHOD_CASH,
        'paid_at' => now()->toDateString(),
        'source' => Payment::SOURCE_STUDENT_SUBMISSION,
        'status' => Payment::STATUS_PENDING,
    ]);

    Sanctum::actingAs($this->fx['admin'], [], 'sanctum');
});

it('reports the four headline figures', function () {
    $response = getJson('/api/v1/fees/overview?session_id='.$this->fx['session']->id.'&term_id='.$this->fx['term']->id)
        ->assertOk();

    expect($response->json('data.expected'))->toBe('45000.00')
        ->and($response->json('data.verified'))->toBe('15000.00')
        ->and($response->json('data.pending'))->toBe('5000.00')
        ->and($response->json('data.outstanding'))->toBe('30000.00')
        ->and($response->json('data.students_billed'))->toBe(3);
});

it('filters the overview by class', function () {
    $response = getJson(
        '/api/v1/fees/overview?session_id='.$this->fx['session']->id
        .'&term_id='.$this->fx['term']->id
        .'&school_class_id='.$this->fx['jss3']->id
    )->assertOk();

    // Only David, in JSS 3, no payments recorded against him.
    expect($response->json('data.expected'))->toBe('15000.00')
        ->and($response->json('data.verified'))->toBe('0.00')
        ->and($response->json('data.students_billed'))->toBe(1);
});

it('breaks collections down by class', function () {
    $response = getJson('/api/v1/fees/reports/collections?session_id='.$this->fx['session']->id.'&term_id='.$this->fx['term']->id)
        ->assertOk();

    $jss2 = collect($response->json('data'))->firstWhere('class_name', 'JSS 2');
    $jss3 = collect($response->json('data'))->firstWhere('class_name', 'JSS 3');

    expect($jss2['expected'])->toBe('30000.00')
        ->and($jss2['verified'])->toBe('15000.00')
        ->and($jss2['students'])->toBe(2)
        ->and($jss3['expected'])->toBe('15000.00')
        ->and($jss3['verified'])->toBe('0.00');
});

it('lists only students with an outstanding balance', function () {
    $response = getJson('/api/v1/fees/reports/outstanding?session_id='.$this->fx['session']->id.'&term_id='.$this->fx['term']->id)
        ->assertOk();

    $names = collect($response->json('data'))->pluck('student_name');

    // John is fully paid, so he is not in the outstanding list.
    expect($names)->toContain('Mary James', 'David Okoro')
        ->not->toContain('John Doe');
});

it('exports the outstanding report as csv', function () {
    $response = getJson('/api/v1/fees/reports/outstanding.csv?session_id='.$this->fx['session']->id.'&term_id='.$this->fx['term']->id);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('Mary James');
});

it('requires the reports permission when enforcement is on', function () {
    config(['features.enforce_endpoint_permissions' => true]);

    $bursar = \App\Models\User::factory()->create([
        'school_id' => $this->fx['school']->id,
        'role' => 'staff',
        'status' => 'active',
    ]);

    app('auth')->forgetGuards();
    Sanctum::actingAs($bursar, [], 'sanctum');

    getJson('/api/v1/fees/overview')->assertForbidden();
    getJson('/api/v1/fees/reports/outstanding')->assertForbidden();
});

it('narrows expected and verified to one fee type', function () {
    // A second fee so the filter has something to exclude.
    app(FeeAssignmentService::class)->create($this->fx['school'], [
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Excursion Fee')->id,
        'amount' => '5000',
    ], $this->fx['admin']);
    app(BillGenerationService::class)->generateForSchool(
        $this->fx['school']->id,
        $this->fx['session']->id,
        $this->fx['term']->id,
    );

    $levyItemId = FeeStructure::whereHas(
        'feeItem',
        fn ($q) => $q->where('name', 'Development Levy')
    )->value('fee_item_id');

    // John's payment from beforeEach is verified but was never allocated --
    // recording money without breaking it down by fee is a real admin
    // workflow, so allocate it here to give the fee-scoped filter something
    // to find.
    $johnsPayment = Payment::where('student_id', $this->fx['john']->id)->firstOrFail();
    $levyItem = \App\Models\StudentBillItem::where('fee_item_id', $levyItemId)
        ->whereHas('bill', fn ($q) => $q->where('student_id', $this->fx['john']->id))
        ->firstOrFail();
    app(\App\Services\Fees\PaymentAllocationService::class)->allocate(
        $johnsPayment,
        [['student_bill_item_id' => $levyItem->id, 'amount' => '15000']],
        $this->fx['admin'],
    );

    $response = getJson(
        '/api/v1/fees/overview?session_id='.$this->fx['session']->id
        .'&term_id='.$this->fx['term']->id
        .'&fee_item_id='.$levyItemId
    )->assertOk();

    // Only the Levy (15,000 x 3 students), not the Excursion Fee.
    expect($response->json('data.expected'))->toBe('45000.00')
        ->and($response->json('data.verified'))->toBe('15000.00');
});

it('reports an unallocated verified payment as zero toward any one fee', function () {
    // John's payment from beforeEach is verified but never allocated.
    $levyItemId = FeeStructure::whereHas(
        'feeItem',
        fn ($q) => $q->where('name', 'Development Levy')
    )->value('fee_item_id');

    $response = getJson(
        '/api/v1/fees/overview?session_id='.$this->fx['session']->id
        .'&term_id='.$this->fx['term']->id
        .'&fee_item_id='.$levyItemId
    )->assertOk();

    // Verified overall (see the headline-figures test), but zero toward a
    // specific fee until an admin says which fee it paid for.
    expect($response->json('data.verified'))->toBe('0.00');
});

it('narrows verified collections to a date range', function () {
    Payment::create([
        'school_id' => $this->fx['school']->id,
        'student_id' => $this->fx['mary']->id,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'reference' => 'CYF-OLD',
        'amount' => '15000',
        'method' => Payment::METHOD_CASH,
        'paid_at' => '2020-01-01',
        'source' => Payment::SOURCE_ADMIN_MANUAL,
        'status' => Payment::STATUS_VERIFIED,
    ]);

    $response = getJson(
        '/api/v1/fees/overview?session_id='.$this->fx['session']->id
        .'&term_id='.$this->fx['term']->id
        .'&from='.now()->subDays(7)->toDateString()
        .'&to='.now()->toDateString()
    )->assertOk();

    // John's 15,000 (paid today) counts; Mary's 2020 payment does not.
    expect($response->json('data.verified'))->toBe('15000.00');
});

it('filters the outstanding report to one derived status', function () {
    getJson(
        '/api/v1/fees/reports/outstanding?session_id='.$this->fx['session']->id
        .'&term_id='.$this->fx['term']->id
        .'&status=pending_verification'
    )
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_name', 'Mary James');
});
