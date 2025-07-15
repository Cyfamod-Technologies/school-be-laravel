<?php

namespace Tests\Unit;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_subdomain_in_fillable()
    {
        $school = new School();
        $this->assertTrue(in_array('subdomain', $school->getFillable()));
    }
}
