<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SchoolSkillType
 *
 * @property string $id
 * @property string $school_id
 * @property string $skill_category_id
 * @property string $skill_type_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property SkillCategory $skill_category
 * @property SkillType $skill_type
 *
 * @package App\Models
 */
class SchoolSkillType extends Model
{
	protected $table = 'school_skill_types';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $fillable = [
		'school_id',
		'skill_category_id',
		'skill_type_id'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function skill_category()
	{
		return $this->belongsTo(SkillCategory::class);
	}

	public function skill_type()
	{
		return $this->belongsTo(SkillType::class);
	}
}
