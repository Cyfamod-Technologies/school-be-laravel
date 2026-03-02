<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Class AgentPayout
 *
 * @property string $id
 * @property string $agent_id
 * @property float $total_amount
 * @property string $status
 * @property string|null $payment_details
 * @property Carbon $requested_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $completed_at
 * @property string|null $failure_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class AgentPayout extends Model
{
    use HasFactory;

    /**
     * Cache table column lookups per request.
     *
     * @var array<string, array<string, bool>>
     */
    private static array $columnCache = [];

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $table = 'agent_payouts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'total_amount' => 'decimal:2',
        'requested_at' => 'timestamp',
        'approved_at' => 'timestamp',
        'processed_at' => 'timestamp',
        'completed_at' => 'timestamp',
    ];

    protected $fillable = [
        'id',
        'agent_id',
        'total_amount',
        'status',
        'payment_details',
        'requested_at',
        'approved_at',
        'processed_at',
        'completed_at',
        'failure_reason',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function commissions()
    {
        return $this->hasMany(AgentCommission::class, 'payout_id');
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function approve()
    {
        $this->status = 'approved';

        if ($this->hasColumn('approved_at')) {
            $this->approved_at = now();
        }

        return $this->save();
    }

    public function markAsProcessing()
    {
        $this->status = 'processing';

        if ($this->hasColumn('processed_at')) {
            $this->processed_at = now();
        }

        return $this->save();
    }

    public function complete()
    {
        $this->status = 'completed';

        if ($this->hasColumn('completed_at')) {
            $this->completed_at = now();
        }

        return $this->save();
    }

    public function fail($reason)
    {
        $this->status = 'failed';

        if ($this->hasColumn('failure_reason')) {
            $this->failure_reason = $reason;
        }

        return $this->save();
    }

    private function hasColumn(string $column): bool
    {
        $table = $this->getTable();

        if (! array_key_exists($table, self::$columnCache)) {
            self::$columnCache[$table] = [];
        }

        if (! array_key_exists($column, self::$columnCache[$table])) {
            try {
                self::$columnCache[$table][$column] = Schema::hasColumn($table, $column);
            } catch (\Throwable) {
                // If metadata lookup fails (permissions/driver), do not block status updates.
                self::$columnCache[$table][$column] = false;
            }
        }

        return self::$columnCache[$table][$column];
    }
}
