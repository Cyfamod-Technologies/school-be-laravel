<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SchoolWebsite
 */
class SchoolWebsiteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schoolId' => $this->school_id,
            'contractVersion' => $this->contract_version,
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
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
