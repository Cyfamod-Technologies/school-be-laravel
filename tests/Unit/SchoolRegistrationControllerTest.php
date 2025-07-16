<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\SchoolRegistrationController;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchoolRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_registers_a_school_and_creates_an_admin_user()
    {
        $controller = new SchoolRegistrationController();

        $request = new Request([
            'name' => 'Test School',
            'address' => '123 Test Street',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'subdomain' => 'test-school',
        ]);

        $response = $controller->register($request);

        $this->assertEquals(201, $response->getStatusCode());

        $this->assertDatabaseHas('schools', [
            'name' => 'Test School',
            'email' => 'test@example.com',
            'subdomain' => 'test-school',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'admin',
        ]);
    }
}
