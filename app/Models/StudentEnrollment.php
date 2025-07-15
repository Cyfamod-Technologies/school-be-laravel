<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StudentEnrollment
 *
 * @property string $id
 * @property string $student_id
 * @property string $class_section_id
 * @property string $session_id
 * @property string $term_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property ClassSection $class_section
 * @property Session $session
 * @property Student $student
 * @property Term $term
 *
 * @package App\Models
 */
class StudentEnrollment extends Model
{
	protected $table = 'student_enrollments';
	public $incrementing = false;

	protected $fillable = [
		'student_id',
		'class_section_id',
		'session_id',
		'term_id'
	];

	public function class_section()
	{
		return $this->belongsTo(ClassSection::class);
	}

	public function session()
	{
		return $this->belongsTo(Session::class);
	}

	public function student()
	{
		return $this->belongsTo(Student::class);
	}

	public function term()
	{
		return $this->belongsTo(Term::class);
	}
}
