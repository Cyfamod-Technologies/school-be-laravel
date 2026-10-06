<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A discount, surcharge or waiver applied to one bill line, kept as its own
 * record so the change stays answerable long after the bill is settled.
 */
class FeeAdjustment extends Model
{
    use HasUuids;

    public const TYPE_DISCOUNT = 'discount';

    public const TYPE_SURCHARGE = 'surcharge';

    /** A waiver is a discount for the full remaining amount of the line. */
    public const TYPE_WAIVER = 'waiver';

    public const TYPES = [
        self::TYPE_DISCOUNT,
        self::TYPE_SURCHARGE,
        self::TYPE_WAIVER,
    ];

    protected $table = 'fee_adjustments';

    protected $fillable = [
        'school_id',
        'student_bill_item_id',
        'type',
        'amount',
        'reason',
        'created_by',
        'reversed_at',
        'reversed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reversed_at' => 'datetime',
    ];

    public function billItem(): BelongsTo
    {
        return $this->belongsTo(StudentBillItem::class, 'student_bill_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('reversed_at');
    }

    public function isReducing(): bool
    {
        return in_array($this->type, [self::TYPE_DISCOUNT, self::TYPE_WAIVER], true);
    }
}
