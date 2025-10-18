<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Result
 *
 * @property string $id
 * @property string $student_id
 * @property string $subject_id
 * @property string $term_id
 * @property string|null $assessment_component_id
 * @property string $session_id
 * @property float $total_score
 * @property int|null $position_in_subject
 * @property float|null $lowest_in_class
 * @property float|null $highest_in_class
 * @property float|null $class_average
 * @property string|null $grade_id
 * @property string|null $remarks
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property GradeRange|null $grade_range
 * @property Session $session
 * @property Student $student
 * @property Subject $subject
 * @property Term $term
 * @property AssessmentComponent|null $assessment_component
 *
 * @package App\Models
 */
class Result extends Model
{
	protected $table = 'results';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $casts = [
		'total_score' => 'float',
		'position_in_subject' => 'int',
		'lowest_in_class' => 'float',
		'highest_in_class' => 'float',
		'class_average' => 'float'
	];

	public const NULL_COMPONENT_UUID = '00000000-0000-0000-0000-000000000000';

	protected $fillable = [
		'student_id',
		'subject_id',
		'assessment_component_id',
		'component_slot',
		'term_id',
		'session_id',
		'total_score',
		'position_in_subject',
		'lowest_in_class',
		'highest_in_class',
		'class_average',
		'grade_id',
		'remarks'
	];

	public function setAssessmentComponentIdAttribute($value): void
	{
		$this->attributes['assessment_component_id'] = $value;
		$this->attributes['component_slot'] = $value ?? self::NULL_COMPONENT_UUID;
	}

	public function setComponentSlotAttribute($value): void
	{
		$this->attributes['component_slot'] = $value ?? self::NULL_COMPONENT_UUID;
	}

	public function grade_range()
	{
		return $this->belongsTo(GradeRange::class, 'grade_id');
	}

	public function session()
	{
		return $this->belongsTo(Session::class);
	}

	public function student()
	{
		return $this->belongsTo(Student::class);
	}

	public function subject()
	{
		return $this->belongsTo(Subject::class);
	}

	public function term()
	{
		return $this->belongsTo(Term::class);
	}

	public function assessment_component()
	{
		return $this->belongsTo(AssessmentComponent::class);
	}
}
