<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSchoolWebsiteResource;
use App\Models\School;
use App\Models\SchoolWebsite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSchoolWebsiteController extends Controller
{
    /**
     * Called by school-public-web's middleware for every request that
     * arrives on a custom domain, to find out which school it belongs to
     * before rendering anything. Intentionally returns only the slug, not
     * any website content -- the caller still goes through the normal
     * `show` endpoint (and its activation gate) afterwards.
     */
    public function resolveDomain(Request $request): JsonResponse
    {
        $domain = $request->query('domain');

        if (! $domain) {
            return response()->json(['message' => 'domain query parameter is required.'], 422);
        }

        $school = School::query()
            ->where('custom_domain', $domain)
            ->first();

        if (! $school) {
            return response()->json(['message' => 'No school found for that domain.'], 404);
        }

        return response()->json(['slug' => $school->slug]);
    }

    /**
     * Return a school's published public website configuration.
     */
    public function show(
        Request $request,
        string $schoolSlug
    ): JsonResponse {
        // Both conditions are required, not just `published` -- without
        // the `activated` check, the shared school-public-web URL would
        // show real content to anyone the moment a school hits Publish,
        // completely bypassing the Go Live/activation workflow (a real
        // loophole found while designing this: a school could just hand
        // out the shared URL and skip Cyfamod entirely otherwise).
        $website = SchoolWebsite::query()
            ->with('school')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereHas(
                'school',
                function (Builder $query) use ($schoolSlug): void {
                    $query->where('slug', $schoolSlug)
                        ->where('activated', true);
                }
            )
            ->firstOrFail();

        $payload = (
            new PublicSchoolWebsiteResource($website)
        )->resolve($request);

        return response()->json($payload);
    }

    /**
     * Return a school's current website configuration regardless of
     * publication status, for a signed, short-lived preview link only.
     * The `signed` route middleware rejects any request with a missing,
     * expired, or tampered signature before this method ever runs.
     */
    public function preview(
        Request $request,
        string $schoolSlug
    ): JsonResponse {
        $website = SchoolWebsite::query()
            ->with('school')
            ->whereHas(
                'school',
                function (Builder $query) use ($schoolSlug): void {
                    $query->where('slug', $schoolSlug);
                }
            )
            ->firstOrFail();

        $payload = (
            new PublicSchoolWebsiteResource($website)
        )->resolve($request);

        return response()->json($payload);
    }
}
