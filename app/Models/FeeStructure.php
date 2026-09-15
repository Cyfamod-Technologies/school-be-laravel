<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fee assigned to a scope for one session/term.
 *
 * The four scopes stack: a JSS 2A student's bill is the school-wide fees, plus
 * the JSS 2 class fees, plus the JSS 2A arm fees, plus anything assigned to
 * them individually.
 */
class FeeStructure extends Model
{
    use HasUuids;

    public const SCOPE_SCHOOL = 'school';

    public const SCOPE_CLASS = 'class';

    public const SCOPE_CLASS_ARM = 'class_arm';

    public const SCOPE_STUDENT = 'student';

    public const SCOPES = [
        self::SCOPE_SCHOOL,
        self::SCOPE_CLASS,
        self::SCOPE_CLASS_ARM,
        self::SCOPE_STUDENT,
    ];

    protected $table = 'fee_structures';

    protected $fillable = [
        'school_id',
        'scope',
        'school_class_id',
        'class_arm_id',
        'session_id',
        'term_id',
        'fee_item_id',
        'amount',
        'description',
        'due_date',
        'is_mandatory',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $feeStructure) {
            $feeStructure->assignment_key = self::makeAssignmentKey(
                $feeStructure->school_id,
                $feeStructure->scope,
                $feeStructure->school_class_id,
                $feeStructure->class_arm_id,
                $feeStructure->session_id,
                $feeStructure->term_id,
                $feeStructure->fee_item_id,
            );
        });
    }

    /**
     * The natural key for an assignment, used by the unique index that stops a
     * school billing the same fee item to the same scope twice in one term.
     *
     * Student-scoped assignments have no natural key -- two excursions in one
     * term are legitimate, and the students they target live in a pivot rather
     * than on the row -- so they get NULL, which a unique index tolerates in
     * any number.
     */
    public static function makeAssignmentKey(
        ?string $schoolId,
        ?string $scope,
        ?string $schoolClassId,
        ?string $classArmId,
        ?string $sessionId,
        ?string $termId,
        ?string $feeItemId,
    ): ?string {
        if ($scope === self::SCOPE_STUDENT) {
            return null;
        }

        return hash('sha256', implode('|', [
            (string) $schoolId,
            (string) $scope,
            (string) $schoolClassId,
            (string) $classArmId,
            (string) $sessionId,
            (string) $termId,
            (string) $feeItemId,
        ]));
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /**
     * Kept under its original name because the web client reads `class` off
     * the fee-structure payload.
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'school_class_id');
    }

    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class, 'class_arm_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Targets of a student-scoped assignment. Empty for every other scope.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'fee_structure_students')
            ->withTimestamps();
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(StudentBillItem::class);
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForClass($query, $schoolClassId)
    {
        return $query->where('school_class_id', $schoolClassId);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeForTerm($query, $termId)
    {
        return $query->where('term_id', $termId);
    }

    public function scopeForScope($query, string $scope)
    {
        return $query->where('scope', $scope);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Total of the class-scoped fees for a class in a term. Does not include
     * school-wide, arm or individual fees -- use BillCalculator for a figure a
     * student would recognise as their bill.
     */
    public static function getTotalForClassSessionTerm($schoolClassId, $sessionId, $termId)
    {
        return self::query()
            ->where('scope', self::SCOPE_CLASS)
            ->where('school_class_id', $schoolClassId)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->sum('amount');
    }
}
