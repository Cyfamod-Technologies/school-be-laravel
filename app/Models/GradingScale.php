<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GradingScale
 *
 * @property string $id
 * @property string $school_id
 * @property string|null $session_id
 * @property string $name
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property Session|null $session
 * @property Collection|GradeRange[] $grade_ranges
 * @property Collection|PositionRange[] $position_ranges
 *
 * @package App\Models
 */
class GradingScale extends Model
{
	protected $table = 'grading_scales';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'school_id',
		'session_id',
		'name',
		'description'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function session()
	{
		return $this->belongsTo(Session::class);
	}

	public function grade_ranges()
	{
		return $this->hasMany(GradeRange::class);
	}

	public function position_ranges()
	{
		return $this->hasMany(PositionRange::class);
	}
}
