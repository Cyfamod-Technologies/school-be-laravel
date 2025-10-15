<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TermSummary
 *
 * @property string $id
 * @property string $student_id
 * @property string $session_id
 * @property string $term_id
 * @property int $total_marks_obtained
 * @property int $total_marks_possible
 * @property float $average_score
 * @property int $position_in_class
 * @property float $class_average_score
 * @property int|null $days_present
 * @property int|null $days_absent
 * @property string|null $final_grade
 * @property string|null $overall_comment
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Session $session
 * @property Student $student
 * @property Term $term
 *
 * @package App\Models
 */
class TermSummary extends Model
{
	protected $table = 'term_summaries';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $casts = [
		'total_marks_obtained' => 'int',
		'total_marks_possible' => 'int',
		'average_score' => 'float',
		'position_in_class' => 'int',
		'class_average_score' => 'float',
		'days_present' => 'int',
		'days_absent' => 'int'
	];

	protected $fillable = [
		'student_id',
		'session_id',
		'term_id',
		'total_marks_obtained',
		'total_marks_possible',
		'average_score',
		'position_in_class',
		'class_average_score',
		'days_present',
		'days_absent',
		'final_grade',
		'overall_comment'
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
