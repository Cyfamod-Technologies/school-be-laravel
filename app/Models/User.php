<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Class User
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role
 * @property string $status
 * @property Carbon|null $last_login
 * @property Carbon|null $email_verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Collection|AuditLog[] $audit_logs
 * @property Collection|MessageThread[] $message_threads
 * @property Collection|Message[] $messages
 * @property Collection|Parent[] $parents
 * @property Collection|School[] $schools
 * @property Collection|Staff[] $staff
 *
 * @package App\Models
 */
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="role", type="string", enum={"staff", "parent", "super_admin", "accountant"}),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}),
 *     @OA\Property(property="last_login", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class User extends Model
{
	use HasFactory, HasApiTokens;
	protected $table = 'users';
	public $incrementing = false;
	protected $keyType = 'string';

	protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

	protected $casts = [
		'last_login' => 'datetime',
		'email_verified_at' => 'datetime'
	];

	protected $hidden = [
		'password'
	];

	protected $fillable = [
		'name',
		'email',
		'password',
		'role',
		'status',
		'last_login',
		'email_verified_at',
		'school_id'
	];

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
		return $this->hasMany(Parent::class);
	}

	public function schools()
	{
		return $this->belongsToMany(School::class, 'school_user_assignments')
					->withPivot('id')
					->withTimestamps();
	}

	public function staff()
	{
		return $this->hasMany(Staff::class);
	}
}
