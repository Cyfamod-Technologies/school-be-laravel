<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AssessmentComponent
 *
 * @property string $id
 * @property string $school_id
 * @property string $session_id
 * @property string $term_id
 * @property string $name
 * @property float $weight
 * @property int $order
 * @property string|null $label
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property Session $session
 * @property Term $term
 *
 * @package App\Models
 */
class AssessmentComponent extends Model
{
	protected $table = 'assessment_components';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $casts = [
		'weight' => 'float',
		'order' => 'int'
	];

	protected $fillable = [
		'id',
		'school_id',
		'session_id',
		'term_id',
		'name',
		'weight',
		'order',
		'label'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function session()
	{
		return $this->belongsTo(Session::class);
	}

	public function term()
	{
		return $this->belongsTo(Term::class);
	}

	public function subjects()
	{
		return $this->belongsToMany(
			Subject::class,
			'assessment_component_subject',
			'assessment_component_id',
			'subject_id'
		);
	}

	public function results()
	{
		return $this->hasMany(Result::class, 'assessment_component_id');
	}
}
