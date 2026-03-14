<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentPayoutGateTest extends TestCase
{
    use RefreshDatabase;

    private PayoutService $payoutService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payoutService = app(PayoutService::class);
    }

    /** @test */
    public function it_prevents_payout_requests_from_pending_agents()
    {
        // Create a pending agent
        $agent = Agent::factory()->create([
            'status' => 'pending'
        ]);

        $school = \App\Models\School::factory()->create();
        $referral = \App\Models\Referral::create([
            'agent_id' => $agent->id,
            'school_id' => $school->id,
            'referral_code' => 'P_TEST',
            'referral_link' => 'http://localhost/P_TEST',
            'status' => 'active',
        ]);

        // Give them some approved money (using raw DB to bypass service logic)
        AgentCommission::create([
            'agent_id' => $agent->id,
            'referral_id' => $referral->id,
            'school_id' => $school->id,
            'commission_amount' => 10000,
            'status' => 'approved',
            'payment_number' => 1,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Your account is under review');

        $this->payoutService->requestPayout($agent);
    }

    /** @test */
    public function it_allows_payout_requests_from_approved_agents()
    {
        // Create an approved agent
        $agent = Agent::factory()->create([
            'status' => 'approved'
        ]);

        $school = \App\Models\School::factory()->create();
        $referral = \App\Models\Referral::create([
            'agent_id' => $agent->id,
            'school_id' => $school->id,
            'referral_code' => 'A_TEST',
            'referral_link' => 'http://localhost/A_TEST',
            'status' => 'active',
        ]);

        // Give them enough approved money to meet threshold
        AgentCommission::create([
            'agent_id' => $agent->id,
            'referral_id' => $referral->id,
            'school_id' => $school->id,
            'commission_amount' => 10000,
            'status' => 'approved',
            'payment_number' => 1,
        ]);

        $payout = $this->payoutService->requestPayout($agent);

        $this->assertNotNull($payout);
        $this->assertEquals('pending', $payout->status);
    }
}
