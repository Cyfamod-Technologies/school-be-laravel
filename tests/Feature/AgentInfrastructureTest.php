<?php

namespace Tests\Feature;

use App\Models\Agent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function new_agents_default_to_pending_status()
    {
        $agent = Agent::factory()->create();
        
        // Assert that the database default is actually 'pending'
        $this->assertEquals('pending', $agent->status);
        $this->assertTrue($agent->isPending());
    }

    /** @test */
    public function agent_status_checks_are_case_insensitive_and_robust()
    {
        $agent = new Agent();
        
        // Test casing
        $agent->status = 'PENDING';
        $this->assertTrue($agent->isPending());
        
        // Test whitespace (defensive check)
        $agent->status = ' pending ';
        $this->assertTrue($agent->isPending());
        
        $agent->status = 'APPROVED';
        $this->assertTrue($agent->isApproved());
    }
}
