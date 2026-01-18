<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentComponentStructure extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'school_id',
        'assessment_component_id',
        'class_id',
        'term_id',
        'max_score',
        'description',
        'is_active',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function assessmentComponent()
    {
        return $this->belongsTo(AssessmentComponent::class);
    }

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function term()
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /**
     * Get the max score for a specific assessment component
     * 
     * Priority order:
     * 1. Specific class + specific term
     * 2. Specific class + any term
     * 3. Any class + specific term
     * 4. Global default (falls back to component's weight)
     */
    public static function getMaxScore(
        string $assessmentComponentId,
        ?string $classId = null,
        ?string $termId = null
    ): ?float {
        $query = static::where('assessment_component_id', $assessmentComponentId)
            ->where('is_active', true);

        // Build scoring logic for priority matching
        $structure = $query
            ->orderByRaw(
                "CASE 
                    WHEN class_id IS NOT NULL AND term_id IS NOT NULL THEN 1
                    WHEN class_id IS NOT NULL THEN 2
                    WHEN term_id IS NOT NULL THEN 3
                    ELSE 4
                END"
            )
            ->where(function ($q) use ($classId) {
                $q->whereNull('class_id')->orWhere('class_id', $classId);
            })
            ->where(function ($q) use ($termId) {
                $q->whereNull('term_id')->orWhere('term_id', $termId);
            })
            ->first();

        if ($structure) {
            return (float) $structure->max_score;
        }

        // Fallback to global assessment component score
        $component = AssessmentComponent::find($assessmentComponentId);
        return $component ? (float) $component->weight : null;
    }

    /**
     * Get all applicable structures for a component and class
     * Useful for showing all available scores for a component
     */
    public static function getApplicableStructures(
        string $assessmentComponentId,
        ?string $classId = null,
        ?string $termId = null
    ) {
        return static::where('assessment_component_id', $assessmentComponentId)
            ->where('is_active', true)
            ->where(function ($q) use ($classId) {
                $q->whereNull('class_id')->orWhere('class_id', $classId);
            })
            ->where(function ($q) use ($termId) {
                $q->whereNull('term_id')->orWhere('term_id', $termId);
            })
            ->get();
    }
}
