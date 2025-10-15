<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Term
 *
 * @property string $id
 * @property string $school_id
 * @property string $session_id
 * @property string $name
 * @property string $slug
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property Session $session
 * @property Collection|AssessmentComponent[] $assessment_components
 * @property Collection|Attendance[] $attendances
 * @property Collection|ClassTeacher[] $class_teachers
 * @property Collection|FeePayment[] $fee_payments
 * @property Collection|Result[] $results
 * @property Collection|SkillRating[] $skill_ratings
 * @property Collection|StudentEnrollment[] $student_enrollments
 * @property Collection|Student[] $students
 * @property Collection|SubjectTeacherAssignment[] $subject_teacher_assignments
 * @property Collection|TermSummary[] $term_summaries
 *
 * @package App\Models
 */
class Term extends Model
{
	protected $table = 'terms';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $casts = [
		'start_date' => 'datetime',
		'end_date' => 'datetime'
	];

	protected $fillable = [
		'id',
		'school_id',
		'session_id',
		'name',
		'slug',
		'start_date',
		'end_date',
		'status'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function session()
	{
		return $this->belongsTo(Session::class);
	}

	public function assessment_components()
	{
		return $this->hasMany(AssessmentComponent::class);
	}

	public function attendances()
	{
		return $this->hasMany(Attendance::class);
	}

	public function class_teachers()
	{
		return $this->hasMany(ClassTeacher::class);
	}

	public function fee_payments()
	{
		return $this->hasMany(FeePayment::class);
	}

	public function results()
	{
		return $this->hasMany(Result::class);
	}

	public function skill_ratings()
	{
		return $this->hasMany(SkillRating::class);
	}

	public function student_enrollments()
	{
		return $this->hasMany(StudentEnrollment::class);
	}

	public function students()
	{
		return $this->hasMany(Student::class, 'current_term_id');
	}

	public function subject_teacher_assignments()
	{
		return $this->hasMany(SubjectTeacherAssignment::class);
	}

	public function term_summaries()
	{
		return $this->hasMany(TermSummary::class);
	}
}
