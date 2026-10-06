<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-school counter behind payment references and receipt numbers.
 */
class FinanceSequence extends Model
{
    use HasUuids;

    public const KIND_PAYMENT_REFERENCE = 'payment_reference';

    public const KIND_RECEIPT_NUMBER = 'receipt_number';

    protected $table = 'finance_sequences';

    protected $fillable = [
        'school_id',
        'kind',
        'next_value',
    ];

    protected $casts = [
        'next_value' => 'integer',
    ];
}
