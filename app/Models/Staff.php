<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Staff
 *
 * @property string $id
 * @property string $school_id
 * @property string $user_id
 * @property string $full_name
 * @property string $email
 * @property string $phone
 * @property string $role
 * @property string $gender
 * @property string|null $address
 * @property string|null $qualifications
 * @property Carbon|null $employment_start_date
 * @property string|null $photo_url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property User $user
 * @package App\Models
 */
class Staff extends Model
{
	protected $table = 'staff';
	public $incrementing = false;
	protected $keyType = 'string';

	use HasUuids;

	protected $casts = [
		'employment_start_date' => 'date',
	];

	protected $fillable = [
		'school_id',
		'user_id',
		'full_name',
		'email',
		'phone',
		'role',
		'gender',
		'address',
		'qualifications',
		'employment_start_date',
		'photo_url'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
