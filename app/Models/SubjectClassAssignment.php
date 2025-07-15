<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SubjectClassAssignment
 *
 * @property string $id
 * @property string $subject_id
 * @property string $class_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Class $class
 * @property Subject $subject
 *
 * @package App\Models
 */
class SubjectClassAssignment extends Model
{
	protected $table = 'subject_class_assignments';
	public $incrementing = false;

	protected $fillable = [
		'subject_id',
		'class_id'
	];

	public function class()
	{
		return $this->belongsTo(Classes::class);
	}

	public function subject()
	{
		return $this->belongsTo(Subject::class);
	}
}
