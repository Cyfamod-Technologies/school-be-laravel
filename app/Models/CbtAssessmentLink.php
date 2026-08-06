<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CbtAssessmentLink extends Model
{
    use HasUuids;

    protected $table = 'cbt_assessment_links';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'assessment_component_id',
        'cbt_exam_id',
        'class_id',
        'term_id',
        'session_id',
        'subject_id',
        'auto_sync',
        'score_mapping_type',
        'max_score_override',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'auto_sync' => 'boolean',
        'is_active' => 'boolean',
        'max_score_override' => 'decimal:2',
    ];

    public function assessmentComponent()
    {
        return $this->belongsTo(AssessmentComponent::class);
    }

    public function cbtExam()
    {
        return $this->belongsTo(Quiz::class, 'cbt_exam_id');
    }

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function imports()
    {
        return $this->hasMany(CbtScoreImport::class, 'cbt_assessment_link_id');
    }

    public function pendingImports()
    {
        return $this->imports()->where('status', 'pending');
    }
}
