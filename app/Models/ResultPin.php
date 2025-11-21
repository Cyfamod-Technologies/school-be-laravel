<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\Session;
use App\Models\Term;
use App\Models\User;

/**
 * Class ResultPin
 *
 * @property string $id
 * @property string $student_id
 * @property string $session_id
 * @property string $term_id
 * @property string $pin_code
 * @property string $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $created_by
 * @property int $use_count
 * @property int|null $max_usage
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Student $student
 * @property Session $session
 * @property Term $term
 * @property User|null $creator
 *
 * @package App\Models
 */
class ResultPin extends Model
{
	protected $table = 'result_pins';
	public $incrementing = false;

	protected $keyType = 'string';

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'use_count' => 'int',
        'max_usage' => 'int',
    ];

    protected $fillable = [
        'id',
        'student_id',
        'session_id',
        'term_id',
        'pin_code',
        'status',
        'expires_at',
        'revoked_at',
        'created_by',
        'use_count',
        'max_usage',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $pin): void {
            if (empty($pin->id)) {
                $pin->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
