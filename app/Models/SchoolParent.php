<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SchoolParent
 *
 * @property string $id
 * @property string $school_id
 * @property string $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $occupation
 * @property string|null $nationality
 * @property string|null $state_of_origin
 * @property string|null $local_government_area
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property User $user
 * @property Collection|Student[] $students
 *
 * @package App\Models
 */
class SchoolParent extends Model
{
	protected $table = 'parents';
	public $incrementing = false;

	protected $fillable = [
		'id',
		'school_id',
		'user_id',
		'first_name',
		'last_name',
		'middle_name',
		'phone',
		'address',
		'occupation',
		'nationality',
		'state_of_origin',
		'local_government_area'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function students()
	{
		return $this->hasMany(Student::class);
	}
}
