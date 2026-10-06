<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who did what to which financial record, and what it looked like either side
 * of the change.
 */
class FinanceAuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const ACTOR_USER = 'user';

    public const ACTOR_STUDENT = 'student';

    public const ACTOR_SYSTEM = 'system';

    protected $table = 'finance_audit_logs';

    protected $fillable = [
        'school_id',
        'actor_type',
        'actor_id',
        'actor_name',
        'action',
        'subject_type',
        'subject_id',
        'before',
        'after',
        'ip_address',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
