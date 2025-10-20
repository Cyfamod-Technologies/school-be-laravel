<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

/**
 * Class School
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $subdomain
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
 * @property Collection|AnalyticsDatum[] $analytics_data
 * @property Collection|AssessmentComponent[] $assessment_components
 * @property Collection|Class[] $classes
 * @property Collection|GradingScale[] $grading_scales
 * @property Collection|SchoolParent[] $parents
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
	use HasFactory;

	protected static function booted()
	{
		static::creating(function (self $model) {
			if (empty($model->id)) {
				$model->id = (string) Str::uuid();
			}
		});
	}

	protected $table = 'schools';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $casts = [
		'established_at' => 'datetime'
	];

	protected $fillable = [
		'id',
		'name',
		'slug',
		'subdomain',
		'address',
		'email',
		'phone',
		'logo_url',
		'signature_url',
		'established_at',
		'owner_name',
		'status',
		'current_session_id',
		'current_term_id',
	];

	public function analytics_data()
	{
		return $this->hasMany(AnalyticsDatum::class);
	}

	public function assessment_components()
	{
		return $this->hasMany(AssessmentComponent::class);
	}

	public function classes()
	{
		return $this->hasMany(\App\Models\Classes::class);
	}

	public function grading_scales()
	{
		return $this->hasMany(GradingScale::class);
	}

	public function parents()
	{
		return $this->hasMany(SchoolParent::class);
	}

	public function skill_types()
	{
		return $this->hasMany(SkillType::class);
	}

	public function users()
	{
		return $this->hasMany(User::class);
	}

	public function getLogoUrlAttribute($value)
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (Str::startsWith($value, ['http://', 'https://'])) {
			return $value;
		}

		$appUrl = rtrim(config('app.url'), '/');

		if (Str::startsWith($value, '/storage/')) {
			return $appUrl . $value;
		}

		return $appUrl . Storage::url($value);
	}

	public function getSignatureUrlAttribute($value)
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (Str::startsWith($value, ['http://', 'https://'])) {
			return $value;
		}

		$appUrl = rtrim(config('app.url'), '/');

		if (Str::startsWith($value, '/storage/')) {
			return $appUrl . $value;
		}

		return $appUrl . Storage::url($value);
	}

	public function currentSession()
	{
		return $this->belongsTo(Session::class, 'current_session_id');
	}

	public function currentTerm()
	{
		return $this->belongsTo(Term::class, 'current_term_id');
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

	public function feeItems()
	{
		return $this->hasMany(FeeItem::class);
	}

	public function feeStructures()
	{
		return $this->hasMany(FeeStructure::class);
	}

	public function bankDetails()
	{
		return $this->hasMany(BankDetail::class);
	}
}
