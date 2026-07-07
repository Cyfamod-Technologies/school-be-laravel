<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSchoolWebsiteResource;
use App\Models\SchoolWebsite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSchoolWebsiteController extends Controller
{
    /**
     * Return a school's published public website configuration.
     */
    public function show(
        Request $request,
        string $schoolSlug
    ): JsonResponse {
        $website = SchoolWebsite::query()
            ->with('school')
            ->where('status', 'published')
            ->whereNotNull('published_at')
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
