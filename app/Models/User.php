<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;


/**
 * Class User
 *
 * @property string $id
 * @property string|null $school_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role
 * @property string $status
 * @property Carbon|null $last_login
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School|null $school
 * @property Collection|AuditLog[] $audit_logs
 * @property Collection|MessageThread[] $message_threads
 * @property Collection|Message[] $messages
 * @property Collection|SchoolParent[] $parents
 * @property Collection|School[] $schools
 * @property Collection|Staff[] $staff
 *
 * @package App\Models
 */
class User extends Authenticatable
{
	use HasFactory;
	use HasApiTokens;
	use HasRoles;

	/**
	 * Guard name used by spatie/laravel-permission.
	 *
	 * @var string
	 */
	protected $guard_name = 'sanctum';
	protected $table = 'users';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $casts = [
		'last_login' => 'datetime',
		'email_verified_at' => 'datetime'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'id',
		'school_id',
		'name',
		'email',
		'password',
		'role',
		'status',
		'last_login',
		'email_verified_at',
		'remember_token',
		'phone',
		'address',
		'occupation',
		'nationality',
		'state_of_origin',
		'local_government_area',
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function staff()
	{
		return $this->hasOne(Staff::class);
	}

	public function audit_logs()
	{
		return $this->hasMany(AuditLog::class);
	}

	public function message_threads()
	{
		return $this->hasMany(MessageThread::class, 'sender_id');
	}

	public function messages()
	{
		return $this->hasMany(Message::class, 'sender_id');
	}

	public function parents()
	{
		return $this->hasMany(SchoolParent::class);
	}

	public function schools()
	{
		return $this->belongsToMany(School::class, 'school_user_assignments')
					->withPivot('id')
					->withTimestamps();
	}

	protected static function booted()
	{
		static::creating(function (self $model) {
			if (empty($model->id)) {
				$model->id = (string) Str::uuid();
			}
		});
	}

	public function getRoleAttribute($value)
	{
		if ($value !== null) {
			return $value;
		}

		return $this->roles
			->firstWhere('guard_name', config('permission.default_guard', 'sanctum'))
			?->name ?? null;
	}
}
