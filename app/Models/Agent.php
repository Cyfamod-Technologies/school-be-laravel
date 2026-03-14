<?php

namespace App\Models;

use Carbon\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * Class Agent
 *
 * @property string $id
 * @property string $full_name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string $phone
 * @property string|null $whatsapp_number
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number
 * @property string|null $bank_name
 * @property string|null $company_name
 * @property string|null $address
 * @property string $status
 * @property Carbon|null $approved_at
 * @property string|null $approved_by
 * @property string|null $rejection_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class Agent extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $table = 'agents';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'email_verified_at' => 'datetime',
        'approved_at' => 'timestamp',
        'password' => 'hashed',
    ];

    protected $fillable = [
        'id',
        'full_name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'whatsapp_number',
        'bank_account_name',
        'bank_account_number',
        'bank_name',
        'company_name',
        'address',
        'status',
        'approved_at',
        'approved_by',
        'rejection_reason',
    ];

    protected $hidden = [
        'password',
    ];

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }

    public function commissions()
    {
        return $this->hasMany(AgentCommission::class);
    }

    public function payouts()
    {
        return $this->hasMany(AgentPayout::class);
    }

    public function isApproved()
    {
        return strtolower(trim((string) $this->status)) === 'approved';
    }

    public function isPending()
    {
        return strtolower(trim((string) $this->status)) === 'pending';
    }

    public function approve($approvedBy)
    {
        $this->status = 'approved';
        $this->approved_by = $approvedBy;
        $this->approved_at = now();
        return $this->save();
    }

    public function reject($reason)
    {
        $this->status = 'inactive';
        $this->rejection_reason = $reason;
        return $this->save();
    }

    public function getTotalReferrals()
    {
        return $this->referrals()->count();
    }

    public function getConvertedReferrals()
    {
        return $this->referrals()->whereIn('status', ['paid', 'active'])->count();
    }

    public function getTotalEarnings()
    {
        return $this->commissions()
            ->whereIn('status', ['approved', 'paid'])
            ->sum('commission_amount');
    }

    public function getPendingEarnings()
    {
        return $this->commissions()
            ->where('status', 'pending')
            ->sum('commission_amount');
    }
}
