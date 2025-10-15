<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Subject
 *
 * @property string $id
 * @property string $school_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property Collection|AnalyticsDatum[] $analytics_data
 * @property Collection|Result[] $results
 * @property Collection|Class[] $classes
 * @property Collection|SubjectTeacherAssignment[] $subject_teacher_assignments
 *
 * @package App\Models
 */
class Subject extends Model
{
	protected $table = 'subjects';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $fillable = [
		'school_id',
		'name',
		'code',
		'description'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function analytics_data()
	{
		return $this->hasMany(AnalyticsDatum::class);
	}

	public function results()
	{
		return $this->hasMany(Result::class);
	}

	public function school_classes()
	{
		return $this->belongsToMany(SchoolClass::class, 'subject_school_class_assignments', 'subject_id', 'school_class_id')
					->withPivot('id')
					->withTimestamps();
	}

	public function subject_teacher_assignments()
	{
		return $this->hasMany(SubjectTeacherAssignment::class);
	}
}
