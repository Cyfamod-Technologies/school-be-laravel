<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SkillCategory
 *
 * @property string $id
 * @property string $school_id
 * @property string $name
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property Collection|SchoolSkillType[] $school_skill_types
 * @property Collection|SkillType[] $skill_types
 *
 * @package App\Models
 */
class SkillCategory extends Model
{
	protected $table = 'skill_categories';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $fillable = [
		'school_id',
		'name',
		'description'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function school_skill_types()
	{
		return $this->hasMany(SchoolSkillType::class);
	}

	public function skill_types()
	{
		return $this->hasMany(SkillType::class);
	}
}
