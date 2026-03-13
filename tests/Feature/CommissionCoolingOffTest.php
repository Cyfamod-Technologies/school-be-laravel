<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionCoolingOffTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $commissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commissionService = app(CommissionService::class);
    }

    /** @test */
    public function it_does_not_auto_approve_commissions_during_cooling_off_period()
    {
        $agent = Agent::factory()->create();
        $school = \App\Models\School::factory()->create();
        $referral = \App\Models\Referral::create([
            'agent_id' => $agent->id,
            'school_id' => $school->id,
            'referral_code' => 'TESTCODE',
            'referral_link' => 'http://localhost/ref/TESTCODE',
            'status' => 'active',
        ]);
        
        // Create a commission that was just created (within 72h window)
        $commission = AgentCommission::create([
            'agent_id' => $agent->id,
            'referral_id' => $referral->id,
            'school_id' => $school->id,
            'commission_amount' => 1000,
            'status' => 'pending',
            'release_at' => now()->addHours(72),
            'payment_number' => 1,
        ]);

        // Trigger earnings calculation (which calls autoApprove)
        $this->commissionService->getAgentEarnings($agent);

        // Assert it is still pending
        $this->assertEquals('pending', $commission->fresh()->status);
    }

    /** @test */
    public function it_auto_approves_commissions_after_cooling_off_period_passes()
    {
        $agent = Agent::factory()->create();
        $school = \App\Models\School::factory()->create();
        $referral = \App\Models\Referral::create([
            'agent_id' => $agent->id,
            'school_id' => $school->id,
            'referral_code' => 'TESTCODE2',
            'referral_link' => 'http://localhost/ref/TESTCODE2',
            'status' => 'active',
        ]);
        
        // Create a commission whose release time has passed
        $commission = AgentCommission::create([
            'agent_id' => $agent->id,
            'referral_id' => $referral->id,
            'school_id' => $school->id,
            'commission_amount' => 1000,
            'status' => 'pending',
            'release_at' => now()->subHour(),
            'payment_number' => 1,
        ]);

        // Trigger earnings calculation
        $this->commissionService->getAgentEarnings($agent);

        // Assert it is now approved
        $this->assertEquals('approved', $commission->fresh()->status);
    }
}
