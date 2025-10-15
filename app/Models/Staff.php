<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Staff
 *
 * @property string $id
 * @property string $school_id
 * @property string $user_id
 * @property string|null $staff_number
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string|null $gender
 * @property Carbon|null $date_of_birth
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $qualification
 * @property string|null $designation
 * @property Carbon|null $employment_date
 * @property string|null $nationality
 * @property string|null $state_of_origin
 * @property string|null $local_government_area
 * @property string|null $profile_picture
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property User $user
 * @property Collection|ClassTeacher[] $class_teachers
 * @property Collection|SubjectTeacherAssignment[] $subject_teacher_assignments
 *
 * @package App\Models
 */
class Staff extends Model
{
	protected $table = 'staff';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $casts = [
		'date_of_birth' => 'datetime',
		'employment_date' => 'datetime'
	];

	protected $fillable = [
		'school_id',
		'user_id',
		'staff_number',
		'first_name',
		'last_name',
		'middle_name',
		'gender',
		'date_of_birth',
		'phone',
		'email',
		'address',
		'qualification',
		'designation',
		'employment_date',
		'nationality',
		'state_of_origin',
		'local_government_area',
		'profile_picture'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function class_teachers()
	{
		return $this->hasMany(ClassTeacher::class);
	}

	public function subject_teacher_assignments()
	{
		return $this->hasMany(SubjectTeacherAssignment::class);
	}
}
