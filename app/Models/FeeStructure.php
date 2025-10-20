<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructure extends Model
{
    use HasUuids;

    protected $table = 'fee_structures';

    protected $fillable = [
        'school_id',
        'class_id',
        'session_id',
        'term_id',
        'fee_item_id',
        'amount',
        'is_mandatory',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_mandatory' => 'boolean',
    ];

    /**
     * Get the school that owns the fee structure.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the class for this fee structure.
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * Get the session for this fee structure.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Get the term for this fee structure.
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Get the fee item for this fee structure.
     */
    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    /**
     * Scope a query to filter by school.
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope a query to filter by class.
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    /**
     * Scope a query to filter by session.
     */
    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope a query to filter by term.
     */
    public function scopeForTerm($query, $termId)
    {
        return $query->where('term_id', $termId);
    }

    /**
     * Scope a query to only include mandatory fee structures.
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Get the total amount for a specific class, session, and term.
     */
    public static function getTotalForClassSessionTerm($classId, $sessionId, $termId)
    {
        return self::where('class_id', $classId)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->sum('amount');
    }
}
