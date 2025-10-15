<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AnalyticsDatum
 *
 * @property string $id
 * @property string $school_id
 * @property string $class_id
 * @property string $subject_id
 * @property float $average_score
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property Class $class
 * @property School $school
 * @property Subject $subject
 *
 * @package App\Models
 */
class AnalyticsDatum extends Model
{
	protected $table = 'analytics_data';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $casts = [
		'average_score' => 'float'
	];

	protected $fillable = [
		'school_id',
		'class_id',
		'subject_id',
		'average_score'
	];

	public function class()
	{
		return $this->belongsTo(Class::class);
	}

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function subject()
	{
		return $this->belongsTo(Subject::class);
	}
}
