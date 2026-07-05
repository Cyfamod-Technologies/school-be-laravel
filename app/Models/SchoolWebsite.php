<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolWebsite extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'contract_version',
        'status',
        'theme_key',
        'branding',
        'seo',
        'header',
        'hero',
        'highlights',
        'about',
        'programmes',
        'admissions',
        'contact',
        'social_links',
        'enabled_sections',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'contract_version' => 'integer',
            'branding' => 'array',
            'seo' => 'array',
            'header' => 'array',
            'hero' => 'array',
            'highlights' => 'array',
            'about' => 'array',
            'programmes' => 'array',
            'admissions' => 'array',
            'contact' => 'array',
            'social_links' => 'array',
            'enabled_sections' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
