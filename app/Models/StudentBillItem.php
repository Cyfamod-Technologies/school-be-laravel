<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single line on a student's bill.
 *
 * `name` is a snapshot: renaming the "Tuition" fee item next year must not
 * change what last year's paid bill says it was for.
 */
class StudentBillItem extends Model
{
    use HasUuids;

    public const SOURCE_SCHOOL = 'school';

    public const SOURCE_CLASS = 'class';

    public const SOURCE_CLASS_ARM = 'class_arm';

    public const SOURCE_STUDENT = 'student';

    public const SOURCE_MANUAL = 'manual';

    protected $table = 'student_bill_items';

    protected $fillable = [
        'school_id',
        'student_bill_id',
        'fee_structure_id',
        'fee_item_id',
        'name',
        'description',
        'source',
        'amount',
        'discount_amount',
        'surcharge_amount',
        'net_amount',
        'is_removed',
        'removed_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'surcharge_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'is_removed' => 'boolean',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(StudentBill::class, 'student_bill_id');
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(FeeAdjustment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_removed', false);
    }
}
