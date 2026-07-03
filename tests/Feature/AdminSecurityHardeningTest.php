<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Role $superAdminRole;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles exist for testing
        $this->superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'sanctum']);
        $this->adminRole = Role::create(['name' => 'admin', 'guard_name' => 'sanctum']);
    }

    private function manuallyAssignRole(User $user, Role $role)
    {
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => 'App\Models\User',
            'model_id' => $user->id,
            'school_id' => $user->school_id
        ]);
    }

    /** @test */
    public function it_blocks_regular_school_admins_from_platform_management_routes()
    {
        $school = School::factory()->create();
        $schoolAdmin = User::factory()->create([
            'school_id' => $school->id,
            'role' => 'admin'
        ]);
        $this->manuallyAssignRole($schoolAdmin, $this->adminRole);

        $response = $this->actingAs($schoolAdmin, 'sanctum')
            ->getJson('/api/v1/admin/agents/pending');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'This area is restricted to Platform Administrators only.');
    }

    /** @test */
    public function it_allows_super_admins_to_access_platform_management_routes()
    {
        $hqSchool = School::factory()->create(['slug' => 'cyfamod-hq']);
        $superAdmin = User::factory()->create([
            'school_id' => $hqSchool->id,
            'role' => 'super_admin'
        ]);
        $this->manuallyAssignRole($superAdmin, $this->superAdminRole);

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/agents/pending');

        $response->assertOk();
    }

    /** @test */
    public function it_can_reset_an_inactive_agent_back_to_pending()
    {
        $hqSchool = School::factory()->create(['slug' => 'cyfamod-hq']);
        $superAdmin = User::factory()->create(['school_id' => $hqSchool->id, 'role' => 'super_admin']);
        $this->manuallyAssignRole($superAdmin, $this->superAdminRole);

        $agent = Agent::factory()->create([
            'status' => 'inactive',
            'approved_at' => now(),
            'approved_by' => 'some-admin-id'
        ]);

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/agents/{$agent->id}/reset-pending");

        $response->assertOk();
        
        $agent = $agent->fresh();
        $this->assertEquals('pending', $agent->status);
        $this->assertNull($agent->approved_at);
        $this->assertNull($agent->approved_by);
    }
}
