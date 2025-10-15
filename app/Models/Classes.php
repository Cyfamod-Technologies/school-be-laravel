<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Class
 *
 * @property string $id
 * @property string $school_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property Collection|ClassArm[] $class_arms
 * @property Collection|Student[] $students
 * @property Collection|Subject[] $subjects
 *
 * @package App\Models
 */
class Classes extends Model
{
	protected $table = 'classes';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $fillable = [
		'school_id',
		'name',
		'slug',
		'description'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function class_arms()
	{
		return $this->hasMany(ClassArm::class);
	}

	public function students()
	{
		return $this->hasMany(Student::class);
	}

	public function subjects()
	{
		return $this->belongsToMany(Subject::class, 'subject_class_assignments')
					->withPivot('id')
					->withTimestamps();
	}
}
