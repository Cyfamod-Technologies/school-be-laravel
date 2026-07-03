<?php

use App\Models\Agent;
use App\Models\School;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

describe('AgentController Security — resolveAgent()', function () {
    beforeEach(function () {
        // Create an approved agent for testing
        $this->agent = Agent::factory()->approved()->create();

        // Create a school and admin user (different auth guard)
        $this->school = School::factory()->create();
        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'admin',
            'status' => 'active',
        ]);
    });

    it('allows authenticated agent to access their own dashboard', function () {
        // Act as the agent using the 'agent' guard
        Sanctum::actingAs($this->agent, [], 'agent');

        $response = getJson(route('agents.dashboard'));

        $response->assertOk();
        $response->assertJsonPath('agent.id', $this->agent->id);
    });

    it('prevents school admin from accessing agent dashboard via agent_id param', function () {
        // Act as the school admin (sanctum guard, NOT agent guard)
        Sanctum::actingAs($this->admin, [], 'sanctum');

        // Try to access agent dashboard by passing agent_id in query string
        $response = getJson(route('agents.dashboard') . '?agent_id=' . $this->agent->id);

        // Should be rejected — school admin is NOT an agent
        $response->assertUnauthorized();
    });

    it('prevents school admin from requesting payouts using agent_id param', function () {
        Sanctum::actingAs($this->admin, [], 'sanctum');

        $response = postJson(route('agents.payouts.request'), [
            'agent_id' => $this->agent->id,
            'amount' => 50000,
        ]);

        $response->assertUnauthorized();
    });

    it('prevents school admin from generating referral codes using agent_id param', function () {
        Sanctum::actingAs($this->admin, [], 'sanctum');

        $response = postJson(route('agents.referrals.generate'), [
            'agent_id' => $this->agent->id,
        ]);

        $response->assertUnauthorized();
    });

    it('returns 401 for completely unauthenticated requests to agent endpoints', function () {
        // No authentication at all
        getJson(route('agents.dashboard'))->assertUnauthorized();
        getJson(route('agents.profile'))->assertUnauthorized();
        postJson(route('agents.referrals.generate'))->assertUnauthorized();
        postJson(route('agents.payouts.request'))->assertUnauthorized();
    });
});

describe('Agent Authentication — Login & Token', function () {
    it('allows agent to register and receive confirmation', function () {
        $response = postJson(route('agents.register'), [
            'full_name' => 'Test Agent',
            'email' => 'test.agent@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'phone' => '+2349012345678',
            'whatsapp_number' => '+2349012345678',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'agent' => ['id', 'email', 'full_name', 'status'],
            'verification_required',
        ]);
        expect($response->json('agent.status'))->toBe('pending');
        expect($response->json('verification_required'))->toBeTrue();
    });

    it('allows agent to login with correct credentials', function () {
        $agent = Agent::factory()->create([
            'email' => 'login.test@example.com',
            'password' => 'MyPassword123!',
            'email_verified_at' => now(),
        ]);

        $response = postJson(route('agents.login'), [
            'email' => 'login.test@example.com',
            'password' => 'MyPassword123!',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['agent', 'token']);
        expect($response->json('agent.id'))->toBe($agent->id);
    });

    it('rejects agent login with wrong password', function () {
        Agent::factory()->create([
            'email' => 'wrong.pass@example.com',
            'password' => 'CorrectPassword123!',
            'email_verified_at' => now(),
        ]);

        $response = postJson(route('agents.login'), [
            'email' => 'wrong.pass@example.com',
            'password' => 'WrongPassword!',
        ]);

        // Laravel throws ValidationException which returns 422
        $response->assertStatus(422);
    });
});
