<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Publishes the newest build number live on each app's store listing, so the
 * mobile force-update modal can tell whether the installed binary is behind.
 *
 * Build numbers, not version names: the mobile app compares Android
 * `versionCode` / iOS `CFBundleVersion`, which the stores enforce as strictly
 * increasing. Version names are set from the app config at build time and are
 * not guaranteed to change between releases, so they cannot be relied on to
 * signal an update.
 *
 * `show` is public — the check runs before login, and a mobile binary cannot
 * hold a secret. It returns nothing sensitive: the number is already visible
 * on the store listing. `publish` is server-to-server and carries a shared
 * key, following the same pattern as AccountLookupController and
 * QueueWorkerController.
 */
class AppVersionController extends Controller
{
    private const APPS = ['staff', 'student'];

    private const PLATFORMS = ['ios', 'android'];

    /**
     * GET /api/v1/get-app-version?app=student&platform=android
     *
     * Deliberately not rate limited. Every install calls this on launch, and
     * a school's students share one NAT address, so a per-IP throttle would
     * 429 an entire school at assembly time. The mobile client treats a 404
     * as "not published yet" and skips silently, but reports anything else to
     * Sentry — so a throttle would turn normal traffic into alert noise while
     * quietly disabling the very check this endpoint exists to enable. The
     * handler is a single indexed lookup on a table with at most four rows.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app' => ['required', Rule::in(self::APPS)],
            'platform' => ['required', Rule::in(self::PLATFORMS)],
        ]);

        $version = AppVersion::query()
            ->where('app', $validated['app'])
            ->where('platform', $validated['platform'])
            ->first();

        // No row means nothing has been published to that listing yet — a
        // real state for an app with no iOS release. 404 rather than a zero
        // or a guess: "we do not know" must never be answerable in a way that
        // could compare as an available update.
        if (! $version) {
            return response()->json([
                'message' => 'No published build for this app.',
            ], 404);
        }

        return response()
            ->json(['build' => $version->build])
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * POST /api/v1/app-version
     * Header: X-App-Version-Key
     * Body: { "app": "student", "platform": "android", "build": 17, "force": false }
     *
     * Called by the mobile release pipeline once a build has actually been
     * accepted by the store, which is the only moment the number becomes
     * true. Doing it by hand is what made this feature dormant before.
     */
    public function publish(Request $request): JsonResponse
    {
        $configuredKey = (string) config('services.app_version.publish_key');
        $providedKey = (string) $request->header('X-App-Version-Key', '');

        // Distinguished from a wrong key so a deployment that was never given
        // the variable is diagnosable from the CI log rather than looking
        // like a credential mismatch.
        if ($configuredKey === '') {
            return response()->json([
                'message' => 'App version publishing is not configured.',
            ], 503);
        }

        if ($providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'app' => ['required', Rule::in(self::APPS)],
            'platform' => ['required', Rule::in(self::PLATFORMS)],
            'build' => ['required', 'integer', 'min:1'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $existing = AppVersion::query()
            ->where('app', $validated['app'])
            ->where('platform', $validated['platform'])
            ->first();

        // Store build numbers only ever increase, so a lower one is a stale
        // or out-of-order publish — a retried CI step, or two release jobs
        // racing. Rejecting it keeps the endpoint safe to call again after a
        // partial failure: re-posting the same number is a no-op success.
        //
        // `force` exists because the opposite mistake is unrecoverable
        // without it. Publishing an erroneously high number would put every
        // user behind a force-update they have no way to satisfy, and a
        // monotonic-only endpoint could never take it back.
        if ($existing && $validated['build'] < $existing->build && ! ($validated['force'] ?? false)) {
            return response()->json([
                'message' => 'Refusing to lower the published build. Send force=true to override.',
                'current' => $existing->build,
                'rejected' => $validated['build'],
            ], 409);
        }

        $version = AppVersion::query()->updateOrCreate(
            ['app' => $validated['app'], 'platform' => $validated['platform']],
            ['build' => $validated['build']],
        );

        return response()->json([
            'app' => $version->app,
            'platform' => $version->platform,
            'build' => $version->build,
        ]);
    }
}
