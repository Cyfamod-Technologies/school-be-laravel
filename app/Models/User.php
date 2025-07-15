<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
 * @property Collection|MessageThread[] $message_threads
 * @property Collection|Message[] $messages
 * @property Collection|Parent[] $parents
 * @property Collection|School[] $schools
 * @property Collection|Staff[] $staff
 *
 * @package App\Models
 */
class User extends Model
{
	protected $table = 'users';
	public $incrementing = false;

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
		'email_verified_at'
	];

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
