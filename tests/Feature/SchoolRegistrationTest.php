<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\School;
use App\Models\User;

class SchoolRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_school_can_be_registered()
    {
        $response = $this->postJson('/api/register-school', [
            'name' => 'Test School',
            'address' => '123 Test Street',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'subdomain' => 'test-school',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'school' => [
                    'id',
                    'name',
                    'slug',
                    'address',
                    'email',
                ],
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ]);

        $this->assertDatabaseHas('schools', [
            'name' => 'Test School',
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'super_admin',
        ]);
    }
}
