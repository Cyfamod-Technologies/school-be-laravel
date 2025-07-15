<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SkillRating
 *
 * @property string $id
 * @property string $student_id
 * @property string $term_id
 * @property string $session_id
 * @property string $skill_type_id
 * @property int $rating_value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Session $session
 * @property SkillType $skill_type
 * @property Student $student
 * @property Term $term
 *
 * @package App\Models
 */
class SkillRating extends Model
{
	protected $table = 'skill_ratings';
	public $incrementing = false;

	protected $casts = [
		'rating_value' => 'int'
	];

	protected $fillable = [
		'student_id',
		'term_id',
		'session_id',
		'skill_type_id',
		'rating_value'
	];

	public function session()
	{
		return $this->belongsTo(Session::class);
	}

	public function skill_type()
	{
		return $this->belongsTo(SkillType::class);
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
