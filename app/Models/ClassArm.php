<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ClassArm
 *
 * @property string $id
 * @property string $class_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Class $class
 * @property Collection|ClassSection[] $class_sections
 * @property Collection|Student[] $students
 *
 * @package App\Models
 */
class ClassArm extends Model
{
	protected $table = 'class_arms';
	public $incrementing = false;

	protected $fillable = [
		'class_id',
		'name',
		'slug',
		'description',
		'color'
	];

	public function class()
	{
		return $this->belongsTo(Class::class);
	}

	public function class_sections()
	{
		return $this->hasMany(ClassSection::class);
	}

	public function students()
	{
		return $this->hasMany(Student::class);
	}
}
