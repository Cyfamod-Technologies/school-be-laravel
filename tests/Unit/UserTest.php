<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_school_id_in_fillable()
    {
        $user = new User();
        $this->assertTrue(in_array('school_id', $user->getFillable()));
    }
}
