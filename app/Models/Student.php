<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Student
 *
 * @property string $id
 * @property string $school_id
 * @property string $admission_no
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string $gender
 * @property Carbon $date_of_birth
 * @property string|null $nationality
 * @property string|null $state_of_origin
 * @property string|null $lga_of_origin
 * @property string $house
 * @property string $club
 * @property string $current_session_id
 * @property string $current_term_id
 * @property string $class_id
 * @property string $class_arm_id
 * @property string|null $class_section_id
 * @property string $parent_id
 * @property Carbon $admission_date
 * @property string|null $photo_url
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Class $class
 * @property ClassArm $class_arm
 * @property ClassSection|null $class_section
 * @property Parent $parent
 * @property School $school
 * @property Session $session
 * @property Term $term
 * @property Collection|Attendance[] $attendances
 * @property Collection|FeePayment[] $fee_payments
 * @property Collection|ResultPin[] $result_pins
 * @property Collection|Result[] $results
 * @property Collection|SkillRating[] $skill_ratings
 * @property Collection|StudentEnrollment[] $student_enrollments
 * @property Collection|TermSummary[] $term_summaries
 *
 * @package App\Models
 */
class Student extends Model
{
	protected $table = 'students';
	public $incrementing = false;

	protected $casts = [
		'date_of_birth' => 'datetime',
		'admission_date' => 'datetime'
	];

	protected $fillable = [
		'school_id',
		'admission_no',
		'first_name',
		'middle_name',
		'last_name',
		'gender',
		'date_of_birth',
		'nationality',
		'state_of_origin',
		'lga_of_origin',
		'house',
		'club',
		'current_session_id',
		'current_term_id',
		'class_id',
		'class_arm_id',
		'class_section_id',
		'parent_id',
		'admission_date',
		'photo_url',
		'status'
	];

	public function class()
	{
		return $this->belongsTo(Class::class);
	}

	public function class_arm()
	{
		return $this->belongsTo(ClassArm::class);
	}

	public function class_section()
	{
		return $this->belongsTo(ClassSection::class);
	}

	public function parent()
	{
		return $this->belongsTo(Parent::class);
	}

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function session()
	{
		return $this->belongsTo(Session::class, 'current_session_id');
	}

	public function term()
	{
		return $this->belongsTo(Term::class, 'current_term_id');
	}

	public function attendances()
	{
		return $this->hasMany(Attendance::class);
	}

	public function fee_payments()
	{
		return $this->hasMany(FeePayment::class);
	}

	public function result_pins()
	{
		return $this->hasMany(ResultPin::class);
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

	public function term_summaries()
	{
		return $this->hasMany(TermSummary::class);
	}
}
