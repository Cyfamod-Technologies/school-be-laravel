<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An uploaded proof-of-payment file.
 *
 * Never expose `path` directly. These files name a student and an amount, so
 * they live on a private disk and are served through an ownership-checked
 * route -- unlike student photos and school logos, which are public by design.
 */
class PaymentEvidence extends Model
{
    use HasUuids;

    protected $table = 'payment_evidence';

    protected $fillable = [
        'payment_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by_type',
        'uploaded_by_id',
    ];

    protected $hidden = [
        'path',
        'disk',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
