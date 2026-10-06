<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One student's bill for one session/term.
 *
 * Carries no totals: ask BillCalculator for those so the student portal and
 * the finance dashboard can never quote different numbers.
 */
class StudentBill extends Model
{
    use HasUuids;

    protected $table = 'student_bills';

    protected $fillable = [
        'school_id',
        'student_id',
        'session_id',
        'term_id',
        'school_class_id',
        'class_arm_id',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class, 'class_arm_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentBillItem::class);
    }

    /**
     * Lines that still count towards what the student owes.
     */
    public function activeItems(): HasMany
    {
        return $this->items()->where('is_removed', false);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForPeriod($query, $sessionId, $termId)
    {
        return $query->where('session_id', $sessionId)->where('term_id', $termId);
    }
}
