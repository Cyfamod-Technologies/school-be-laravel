<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertSchoolWebsiteRequest;
use App\Http\Resources\SchoolWebsiteResource;
use App\Models\SchoolWebsite;
use Illuminate\Http\Request;

class SchoolWebsiteController extends Controller
{
    /**
     * Return the authenticated school's website configuration.
     */
    public function show(Request $request): SchoolWebsiteResource
    {
        $schoolId = $this->resolveSchoolId($request);

        $website = SchoolWebsite::query()
            ->where('school_id', $schoolId)
            ->firstOrFail();

        return new SchoolWebsiteResource($website);
    }

    /**
     * Create or update the authenticated school's website configuration.
     */
    public function upsert(
        UpsertSchoolWebsiteRequest $request
    ): SchoolWebsiteResource {
        $schoolId = $this->resolveSchoolId($request);
        $validated = $request->validated();

        $website = SchoolWebsite::query()->firstOrNew([
            'school_id' => $schoolId,
        ]);

        $website->fill([
            'contract_version' => $validated['contractVersion'],
            'status' => $validated['status'],
            'theme_key' => $validated['themeKey'],
            'branding' => $validated['branding'],
            'seo' => $validated['seo'],
            'header' => $validated['header'],
            'hero' => $validated['hero'],
            'highlights' => $validated['highlights'],
            'about' => $validated['about'],
            'programmes' => $validated['programmes'],
            'admissions' => $validated['admissions'],
            'contact' => $validated['contact'],
            'social_links' => $validated['socialLinks'],
            'enabled_sections' => $validated['enabledSections'],
        ]);

        if (
            $validated['status'] === 'published'
            && $website->published_at === null
        ) {
            $website->published_at = now();
        }

        if ($validated['status'] !== 'published') {
            $website->published_at = null;
        }

        $website->save();
        $website->wasRecentlyCreated = false;

        return new SchoolWebsiteResource(
            $website->refresh()
        );
    }

    /**
     * Resolve the school from the authenticated user.
     */
    private function resolveSchoolId(Request $request): string
    {
        $schoolId = $request->user()?->school_id;

        abort_if(
            empty($schoolId),
            422,
            'Authenticated user is not associated with a school.'
        );

        return (string) $schoolId;
    }
}
