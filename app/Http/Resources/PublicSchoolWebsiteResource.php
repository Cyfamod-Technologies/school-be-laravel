<?php

namespace App\Http\Resources;

use App\Models\SchoolWebsite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolWebsite
 */
class PublicSchoolWebsiteResource extends JsonResource
{
    /**
     * Transform a published website into the public frontend contract.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $school = $this->resource->school;

        return [
            'contractVersion' => $this->contract_version,

            'school' => [
                'name' => $school->getAttribute('name')
                    ?? $school->getAttribute('school_name'),

                'slug' => $school->getAttribute('slug'),

                'logoUrl' => $school->getAttribute('logo_url')
                    ?? $school->getAttribute('logo'),

                'studentPortalUrl' => $school->getAttribute(
                    'student_portal_url'
                ),
            ],

            'website' => [
                'status' => $this->status,
                'themeKey' => $this->theme_key,

                'branding' => $this->branding,
                'seo' => $this->seo,
                'header' => $this->header,
                'hero' => $this->hero,
                'highlights' => $this->highlights,
                'about' => $this->about,
                'programmes' => $this->programmes,
                'admissions' => $this->admissions,
                'contact' => $this->contact,

                'socialLinks' => $this->social_links,

                'enabledSections' => $this->enabled_sections,

                'publishedAt' => $this->published_at?->toISOString(),
                'updatedAt' => $this->updated_at?->toISOString(),
            ],
        ];
    }
}
