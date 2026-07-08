<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CbtScoreImport extends Model
{
    use HasUuids;

    protected $table = 'cbt_score_imports';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'cbt_assessment_link_id',
        'student_id',
        'cbt_raw_score',
        'cbt_max_score',
        'converted_score',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'notes',
    ];

    protected $casts = [
        'cbt_raw_score' => 'decimal:2',
        'cbt_max_score' => 'decimal:2',
        'converted_score' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function link()
    {
        return $this->belongsTo(CbtAssessmentLink::class, 'cbt_assessment_link_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
