<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use Illuminate\Support\Facades\Hash;

class SchoolAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_login_with_correct_credentials()
    {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'school_id' => $school->id,
            'role' => 'staff',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/school-admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
    }

    public function test_school_admin_cannot_login_with_incorrect_credentials()
    {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'school_id' => $school->id,
            'role' => 'staff',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/school-admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_non_admin_user_cannot_login()
    {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'school_id' => $school->id,
            'role' => 'parent',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/school-admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(401);
    }

    public function test_school_admin_can_logout()
    {
        $school = School::factory()->create();
        $user = User::factory()->create([
            'school_id' => $school->id,
            'role' => 'staff',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/school-admin/logout');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logged out successfully']);
    }
}
