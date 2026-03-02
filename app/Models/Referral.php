<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class Referral
 *
 * @property string $id
 * @property string $agent_id
 * @property string|null $school_id
 * @property string $referral_code
 * @property string $referral_link
 * @property string $status
 * @property int $payment_count
 * @property bool $commission_limit_reached
 * @property float|null $first_payment_amount
 * @property Carbon|null $visited_at
 * @property Carbon|null $registered_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $active_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class Referral extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->referral_code)) {
                $model->referral_code = 'AGT-' . strtoupper(Str::random(8));
            }
        });
    }

    protected $table = 'referrals';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'payment_count' => 'integer',
        'commission_limit_reached' => 'boolean',
        'first_payment_amount' => 'decimal:2',
        'visited_at' => 'timestamp',
        'registered_at' => 'timestamp',
        'paid_at' => 'timestamp',
        'active_at' => 'timestamp',
    ];

    protected $fillable = [
        'id',
        'agent_id',
        'school_id',
        'referral_code',
        'referral_link',
        'status',
        'payment_count',
        'commission_limit_reached',
        'first_payment_amount',
        'visited_at',
        'registered_at',
        'paid_at',
        'active_at',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function registrations()
    {
        return $this->hasMany(ReferralRegistration::class);
    }

    public function commissions()
    {
        return $this->hasMany(AgentCommission::class);
    }

    public function recordVisit()
    {
        if (!$this->visited_at) {
            $this->visited_at = now();
            $this->status = 'visited';
            $this->save();
        }
    }

    public function recordRegistration()
    {
        $this->registered_at = now();
        $this->status = 'registered';
        return $this->save();
    }

    public function recordFirstPayment($amount)
    {
        $this->paid_at = now();
        $this->first_payment_amount = $amount;
        $this->status = 'paid';
        return $this->save();
    }

    public function markActive()
    {
        $this->active_at = now();
        $this->status = 'active';
        return $this->save();
    }

    public function getTotalCommissions()
    {
        return $this->commissions()
            ->whereIn('status', ['approved', 'paid'])
            ->sum('commission_amount');
    }
}
