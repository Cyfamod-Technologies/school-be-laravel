<?php

use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['services.account_lookup.key' => 'lookup-test-key']);
});

it('finds admin and staff accounts by email', function () {
    User::factory()->create([
        'email' => 'admin@example.test',
        'role' => 'admin',
    ]);

    postJson('/api/v1/find-account', [
        'account_type' => 'staff',
        'identifier' => 'ADMIN@EXAMPLE.TEST',
    ], ['X-Account-Lookup-Key' => 'lookup-test-key'])
        ->assertOk()
        ->assertExactJson(['found' => true]);
});

it('finds student accounts by admission number', function () {
    $school = School::factory()->create();

    $session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => '2026/2027',
        'slug' => '2026-2027',
        'start_date' => now()->subMonths(1),
        'end_date' => now()->addMonths(9),
    ]);

    $term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'session_id' => $session->id,
        'name' => 'First Term',
        'slug' => 'first-term',
        'start_date' => now()->subMonths(1),
        'end_date' => now()->addMonths(2),
    ]);

    $class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'name' => 'Primary 3',
        'slug' => 'primary-3',
    ]);

    $arm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $class->id,
        'name' => 'A',
        'slug' => 'a',
    ]);

    Student::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'admission_no' => 'SCH/2026/001',
        'first_name' => 'Ada',
        'last_name' => 'Okafor',
        'gender' => 'F',
        'date_of_birth' => '2012-05-14',
        'current_session_id' => $session->id,
        'current_term_id' => $term->id,
        'school_class_id' => $class->id,
        'class_arm_id' => $arm->id,
        'admission_date' => now()->subYears(1),
        'status' => 'active',
    ]);

    postJson('/api/v1/find-account', [
        'account_type' => 'student',
        'identifier' => 'SCH/2026/001',
    ], ['X-Account-Lookup-Key' => 'lookup-test-key'])
        ->assertOk()
        ->assertExactJson(['found' => true]);
});

it('returns false when an account does not exist', function () {
    postJson('/api/v1/find-account', [
        'account_type' => 'staff',
        'identifier' => 'missing@example.test',
    ], ['X-Account-Lookup-Key' => 'lookup-test-key'])
        ->assertOk()
        ->assertExactJson(['found' => false]);
});

it('rejects requests without the server-to-server key', function () {
    postJson('/api/v1/find-account', [
        'account_type' => 'staff',
        'identifier' => 'admin@example.test',
    ])->assertUnauthorized();
});
