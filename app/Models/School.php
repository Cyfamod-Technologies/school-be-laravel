<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class School
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $address
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $logo_url
 * @property Carbon|null $established_at
 * @property string|null $owner_name
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Collection|AssessmentComponent[] $assessment_components
 * @property Collection|Class[] $classes
 * @property Collection|GradingScale[] $grading_scales
 * @property Collection|Parent[] $parents
 * @property Collection|SkillType[] $skill_types
 * @property Collection|User[] $users
 * @property Collection|Session[] $sessions
 * @property Collection|SkillCategory[] $skill_categories
 * @property Collection|Staff[] $staff
 * @property Collection|Student[] $students
 * @property Collection|Subject[] $subjects
 * @property Collection|Term[] $terms
 *
 * @package App\Models
 */
class School extends Model
{
	protected $table = 'schools';
	public $incrementing = false;

	protected $casts = [
		'established_at' => 'datetime'
	];

	protected $fillable = [
		'name',
		'slug',
		'address',
		'email',
		'phone',
		'logo_url',
		'established_at',
		'owner_name',
		'status'
	];

	public function assessment_components()
	{
		return $this->hasMany(AssessmentComponent::class);
	}

	public function classes()
	{
		return $this->hasMany(Class::class);
	}

	public function grading_scales()
	{
		return $this->hasMany(GradingScale::class);
	}

	public function parents()
	{
		return $this->hasMany(Parent::class);
	}

	public function skill_types()
	{
		return $this->hasMany(SkillType::class);
	}

	public function users()
	{
		return $this->belongsToMany(User::class, 'school_user_assignments')
					->withPivot('id')
					->withTimestamps();
	}

	public function sessions()
	{
		return $this->hasMany(Session::class);
	}

	public function skill_categories()
	{
		return $this->hasMany(SkillCategory::class);
	}

	public function staff()
	{
		return $this->hasMany(Staff::class);
	}

	public function students()
	{
		return $this->hasMany(Student::class);
	}

	public function subjects()
	{
		return $this->hasMany(Subject::class);
	}

	public function terms()
	{
		return $this->hasMany(Term::class);
	}
}
