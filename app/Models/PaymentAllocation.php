<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The link between a verified payment and the bill line it paid for.
 */
class PaymentAllocation extends Model
{
    use HasUuids;

    protected $table = 'payment_allocations';

    protected $fillable = [
        'payment_id',
        'student_bill_item_id',
        'amount',
        'allocated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function billItem(): BelongsTo
    {
        return $this->belongsTo(StudentBillItem::class, 'student_bill_item_id');
    }

    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
