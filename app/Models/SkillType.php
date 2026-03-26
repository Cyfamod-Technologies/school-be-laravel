<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Models\SchoolClass;

/**
 * Class SkillType
 *
 * @property string $id
 * @property string $skill_category_id
 * @property string $school_id
 * @property string|null $school_class_id
 * @property string $name
 * @property string|null $description
 * @property float|null $weight
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property SkillCategory $skill_category
 * @property School $school
 * @property Collection|School[] $schools
 * @property Collection|SkillRating[] $skill_ratings
 *
 * @package App\Models
 */
class SkillType extends Model
{
	protected $table = 'skill_types';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $casts = [
		'weight' => 'float'
	];

	protected $fillable = [
		'id',
		'skill_category_id',
		'school_id',
		'school_class_id',
		'name',
		'description',
		'weight'
	];

	public function skill_category()
	{
		return $this->belongsTo(SkillCategory::class);
	}

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function school_class()
	{
		return $this->belongsTo(SchoolClass::class, 'school_class_id');
	}

	public function schools()
	{
		return $this->belongsToMany(School::class, 'school_skill_types')
					->withPivot('id', 'skill_category_id')
					->withTimestamps();
	}

	public function skill_ratings()
	{
		return $this->hasMany(SkillRating::class);
	}
}
