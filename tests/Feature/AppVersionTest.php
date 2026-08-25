<?php

use App\Models\AppVersion;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['services.app_version.publish_key' => 'publish-test-key']);

    // Pest.php leaves RefreshDatabase off, and ('app', 'platform') is unique,
    // so each test starts from a known-empty table rather than inheriting rows
    // from the last run.
    AppVersion::query()->delete();
});

function publishHeaders(): array
{
    return ['X-App-Version-Key' => 'publish-test-key'];
}

it('returns the published build for an app and platform', function () {
    AppVersion::query()->create([
        'app' => 'student',
        'platform' => 'android',
        'build' => 17,
    ]);

    getJson('/api/v1/get-app-version?app=student&platform=android')
        ->assertOk()
        ->assertExactJson(['build' => 17]);
});

it('keeps each app and platform on its own sequence', function () {
    AppVersion::query()->create(['app' => 'staff', 'platform' => 'android', 'build' => 22]);
    AppVersion::query()->create(['app' => 'student', 'platform' => 'android', 'build' => 17]);

    getJson('/api/v1/get-app-version?app=staff&platform=android')
        ->assertOk()
        ->assertExactJson(['build' => 22]);

    getJson('/api/v1/get-app-version?app=student&platform=android')
        ->assertOk()
        ->assertExactJson(['build' => 17]);
});

it('does not leak one platform build into the other', function () {
    AppVersion::query()->create(['app' => 'student', 'platform' => 'android', 'build' => 17]);

    getJson('/api/v1/get-app-version?app=student&platform=ios')
        ->assertNotFound();
});

it('404s when nothing has been published for that app yet', function () {
    getJson('/api/v1/get-app-version?app=staff&platform=ios')
        ->assertNotFound();
});

it('rejects an unknown app or platform', function () {
    getJson('/api/v1/get-app-version?app=parent&platform=android')
        ->assertStatus(422);

    getJson('/api/v1/get-app-version?app=student&platform=windows')
        ->assertStatus(422);

    getJson('/api/v1/get-app-version')
        ->assertStatus(422);
});

it('needs no authentication to read', function () {
    AppVersion::query()->create(['app' => 'student', 'platform' => 'android', 'build' => 17]);

    getJson('/api/v1/get-app-version?app=student&platform=android')
        ->assertOk();
});

it('publishes a build number with the server-to-server key', function () {
    postJson('/api/v1/app-version', [
        'app' => 'student',
        'platform' => 'android',
        'build' => 17,
    ], publishHeaders())
        ->assertOk()
        ->assertExactJson(['app' => 'student', 'platform' => 'android', 'build' => 17]);

    getJson('/api/v1/get-app-version?app=student&platform=android')
        ->assertOk()
        ->assertExactJson(['build' => 17]);
});

it('overwrites rather than duplicating on a second publish', function () {
    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 17,
    ], publishHeaders())->assertOk();

    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 18,
    ], publishHeaders())->assertOk();

    expect(AppVersion::query()->where('app', 'student')->where('platform', 'android')->count())
        ->toBe(1);

    getJson('/api/v1/get-app-version?app=student&platform=android')
        ->assertExactJson(['build' => 18]);
});

it('treats re-publishing the same build as a successful no-op', function () {
    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 17,
    ], publishHeaders())->assertOk();

    // A retried CI step after a partial failure must not error.
    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 17,
    ], publishHeaders())
        ->assertOk()
        ->assertExactJson(['app' => 'student', 'platform' => 'android', 'build' => 17]);
});

it('refuses to lower the published build', function () {
    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 18,
    ], publishHeaders())->assertOk();

    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 17,
    ], publishHeaders())
        ->assertStatus(409)
        ->assertJson(['current' => 18, 'rejected' => 17]);

    getJson('/api/v1/get-app-version?app=student&platform=android')
        ->assertExactJson(['build' => 18]);
});

it('lowers the published build when forced', function () {
    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 9999,
    ], publishHeaders())->assertOk();

    // The recovery path for an erroneously high publish, which would
    // otherwise force-update every user with no way to satisfy it.
    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 17, 'force' => true,
    ], publishHeaders())->assertOk();

    getJson('/api/v1/get-app-version?app=student&platform=android')
        ->assertExactJson(['build' => 17]);
});

it('rejects a publish without the server-to-server key', function () {
    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 17,
    ])->assertUnauthorized();

    expect(AppVersion::query()->count())->toBe(0);
});

it('rejects a publish with the wrong key', function () {
    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 17,
    ], ['X-App-Version-Key' => 'not-the-key'])->assertUnauthorized();

    expect(AppVersion::query()->count())->toBe(0);
});

it('reports unconfigured publishing separately from a bad key', function () {
    config(['services.app_version.publish_key' => null]);

    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 17,
    ], publishHeaders())->assertStatus(503);
});

it('rejects an invalid build number', function () {
    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 0,
    ], publishHeaders())->assertStatus(422);

    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android', 'build' => 'seventeen',
    ], publishHeaders())->assertStatus(422);

    postJson('/api/v1/app-version', [
        'app' => 'student', 'platform' => 'android',
    ], publishHeaders())->assertStatus(422);
});
