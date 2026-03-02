<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class ReferralRegistration
 *
 * @property string $id
 * @property string $referral_id
 * @property string $school_id
 * @property Carbon|null $registered_at
 * @property int $payment_count
 * @property float|null $first_payment_amount
 * @property Carbon|null $paid_at
 * @property Carbon|null $active_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReferralRegistration extends Model
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

    protected $table = 'referral_registrations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'registered_at' => 'timestamp',
        'payment_count' => 'integer',
        'first_payment_amount' => 'decimal:2',
        'paid_at' => 'timestamp',
        'active_at' => 'timestamp',
    ];

    protected $fillable = [
        'id',
        'referral_id',
        'school_id',
        'registered_at',
        'payment_count',
        'first_payment_amount',
        'paid_at',
        'active_at',
    ];

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}

