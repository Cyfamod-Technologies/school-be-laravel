<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ClassSection
 *
 * @property string $id
 * @property string $class_arm_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property ClassArm $class_arm
 * @property Collection|ClassTeacher[] $class_teachers
 * @property Collection|StudentEnrollment[] $student_enrollments
 * @property Collection|Student[] $students
 * @property Collection|SubjectTeacherAssignment[] $subject_teacher_assignments
 *
 * @package App\Models
 */
class ClassSection extends Model
{
	protected $table = 'class_sections';
	public $incrementing = false;

	protected $fillable = [
		'class_arm_id',
		'name',
		'slug',
		'description'
	];

	public function class_arm()
	{
		return $this->belongsTo(ClassArm::class);
	}

	public function class_teachers()
	{
		return $this->hasMany(ClassTeacher::class);
	}

	public function student_enrollments()
	{
		return $this->hasMany(StudentEnrollment::class);
	}

	public function students()
	{
		return $this->hasMany(Student::class);
	}

	public function subject_teacher_assignments()
	{
		return $this->hasMany(SubjectTeacherAssignment::class);
	}
}
