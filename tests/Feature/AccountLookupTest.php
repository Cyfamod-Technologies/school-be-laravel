<?php

use App\Models\School;
use App\Models\Student;
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

    Student::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'admission_no' => 'SCH/2026/001',
        'first_name' => 'Ada',
        'last_name' => 'Okafor',
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
