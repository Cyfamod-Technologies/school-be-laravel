<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Attendance
 *
 * @property string $id
 * @property string $student_id
 * @property string $session_id
 * @property string $term_id
 * @property Carbon $date
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Session $session
 * @property Student $student
 * @property Term $term
 *
 * @package App\Models
 */
class Attendance extends Model
{
	protected $table = 'attendances';
	public $incrementing = false;

	protected $casts = [
		'date' => 'datetime'
	];

	protected $fillable = [
		'student_id',
		'session_id',
		'term_id',
		'date',
		'status'
	];

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
