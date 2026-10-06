<?php

use App\Models\FeeStructure;
use App\Models\StudentBill;
use App\Models\StudentBillItem;
use App\Services\Fees\FeeAssignmentService;
use Tests\Support\FeesFixture;

beforeEach(function () {
    $this->fx = FeesFixture::make();

    app(FeeAssignmentService::class)->create($this->fx['school'], [
        'scope' => FeeStructure::SCOPE_SCHOOL,
        'session_id' => $this->fx['session']->id,
        'term_id' => $this->fx['term']->id,
        'fee_item_id' => FeesFixture::feeItem($this->fx['school'], 'Development Levy')->id,
        'amount' => '15000',
    ], $this->fx['admin']);
});

it('generates bills for the school current period by default', function () {
    $this->artisan('fees:generate-bills')->assertSuccessful();

    expect(StudentBill::count())->toBe(3)
        ->and(StudentBillItem::sum('net_amount'))->toEqual('45000.00');
});

it('writes nothing on a dry run', function () {
    $this->artisan('fees:generate-bills', ['--dry-run' => true])->assertSuccessful();

    expect(StudentBill::count())->toBe(0);
});

it('is safe to run twice', function () {
    $this->artisan('fees:generate-bills')->assertSuccessful();
    $this->artisan('fees:generate-bills')->assertSuccessful();

    expect(StudentBillItem::count())->toBe(3);
});

it('fails loudly when a school has no current session or term', function () {
    $this->fx['school']->forceFill([
        'current_session_id' => null,
        'current_term_id' => null,
    ])->save();

    // A misconfigured school has to reach the exit code, not just scrollback.
    $this->artisan('fees:generate-bills')->assertFailed();

    expect(StudentBill::count())->toBe(0);
});

it('refuses a session and term that belong to another school', function () {
    $other = FeesFixture::make();

    $this->artisan('fees:generate-bills', [
        '--school' => $this->fx['school']->id,
        '--session' => $other['session']->id,
        '--term' => $other['term']->id,
    ])->assertFailed();

    expect(StudentBill::count())->toBe(0);
});
