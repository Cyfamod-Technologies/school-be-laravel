<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class AgentCommission
 *
 * @property string $id
 * @property string $agent_id
 * @property string $referral_id
 * @property string $school_id
 * @property string|null $invoice_id
 * @property int $payment_number
 * @property float $commission_amount
 * @property string $status
 * @property string|null $payout_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class AgentCommission extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $table = 'agent_commissions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'payment_number' => 'integer',
        'commission_amount' => 'decimal:2',
        'release_at' => 'datetime',
    ];

    protected $fillable = [
        'id',
        'agent_id',
        'referral_id',
        'school_id',
        'invoice_id',
        'payment_number',
        'commission_amount',
        'status',
        'payout_id',
        'release_at',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payout()
    {
        return $this->belongsTo(AgentPayout::class, 'payout_id');
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function approve()
    {
        $this->status = 'approved';
        return $this->save();
    }

    public function markAsPaid($payoutId)
    {
        $this->status = 'paid';
        $this->payout_id = $payoutId;
        return $this->save();
    }
}
