<?php

use App\Models\School;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->school = School::factory()->create();

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'admin',
        'status' => 'active',
    ]);

    Sanctum::actingAs($this->admin, [], 'sanctum');
});

it('returns the extended result page settings payload', function () {
    getJson('/api/v1/settings/result-page')
        ->assertOk()
        ->assertJsonPath('data.hide_student_identity', false)
        ->assertJsonPath('data.allow_shared_pin_access', false)
        ->assertJsonPath('data.require_pin_for_pdf_download', true)
        ->assertJsonPath('data.enable_session_result_print', false)
        ->assertJsonPath('data.collapse_session_ca', false)
        ->assertJsonPath('data.comment_mode', 'manual');
});

it('updates identity hiding, pin access, and session result settings', function () {
    putJson('/api/v1/settings/result-page', [
        'hide_student_identity' => true,
        'allow_shared_pin_access' => true,
        'require_pin_for_pdf_download' => false,
        'enable_session_result_print' => true,
        'collapse_session_ca' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.hide_student_identity', true)
        ->assertJsonPath('data.allow_shared_pin_access', true)
        ->assertJsonPath('data.require_pin_for_pdf_download', false)
        ->assertJsonPath('data.enable_session_result_print', true)
        ->assertJsonPath('data.collapse_session_ca', true);

    $this->school->refresh();

    expect($this->school->result_hide_student_identity)->toBeTrue()
        ->and($this->school->result_allow_shared_pin_access)->toBeTrue()
        ->and($this->school->result_pdf_requires_pin)->toBeFalse()
        ->and($this->school->result_enable_session_print)->toBeTrue()
        ->and($this->school->result_collapse_session_ca)->toBeTrue();
});
