<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payment at any stage of its life, from submitted evidence to verified
 * money to a reversed mistake.
 *
 * Uploading evidence creates a row here, but a row here is not money: only
 * STATUS_VERIFIED counts towards what a student has paid.
 */
class Payment extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
        self::STATUS_REVERSED,
    ];

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CASH = 'cash';

    public const METHOD_POS = 'pos';

    public const METHOD_CHEQUE = 'cheque';

    public const METHOD_ONLINE = 'online';

    public const METHOD_OTHER = 'other';

    public const METHODS = [
        self::METHOD_BANK_TRANSFER,
        self::METHOD_CASH,
        self::METHOD_POS,
        self::METHOD_CHEQUE,
        self::METHOD_ONLINE,
        self::METHOD_OTHER,
    ];

    public const SOURCE_STUDENT_SUBMISSION = 'student_submission';

    public const SOURCE_ADMIN_MANUAL = 'admin_manual';

    public const ACTOR_STUDENT = 'student';

    public const ACTOR_USER = 'user';

    protected $table = 'payments';

    protected $fillable = [
        'school_id',
        'student_id',
        'session_id',
        'term_id',
        'student_bill_id',
        'reference',
        'receipt_number',
        'payer_reference',
        'amount',
        'method',
        'paid_at',
        'note',
        'source',
        'status',
        'submitted_by_type',
        'submitted_by_id',
        'verified_by',
        'verified_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'reversed_at' => 'datetime',
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

    public function bill(): BelongsTo
    {
        return $this->belongsTo(StudentBill::class, 'student_bill_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(PaymentEvidence::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * The part of a verified payment not yet pinned to a specific fee -- a
     * credit on the student's account, not an error.
     */
    public function unallocatedAmount(): string
    {
        $allocated = (string) $this->allocations()->sum('amount');

        return bcsub((string) $this->amount, $allocated, 2);
    }
}
