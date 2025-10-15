<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GradeRange
 *
 * @property string $id
 * @property string $grading_scale_id
 * @property float $min_score
 * @property float $max_score
 * @property string $grade_label
 * @property string|null $description
 * @property float|null $grade_point
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property GradingScale $grading_scale
 * @property Collection|Result[] $results
 *
 * @package App\Models
 */
class GradeRange extends Model
{
	protected $table = 'grade_ranges';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $casts = [
		'min_score' => 'float',
		'max_score' => 'float',
		'grade_point' => 'float'
	];

	protected $fillable = [
		'grading_scale_id',
		'min_score',
		'max_score',
		'grade_label',
		'description',
		'grade_point'
	];

	public function grading_scale()
	{
		return $this->belongsTo(GradingScale::class);
	}

	public function results()
	{
		return $this->hasMany(Result::class, 'grade_id');
	}
}
