<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SchoolUserAssignment
 *
 * @property string $id
 * @property string $school_id
 * @property string $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property User $user
 *
 * @package App\Models
 */
class SchoolUserAssignment extends Model
{
	protected $table = 'school_user_assignments';
	public $incrementing = false;

	protected $fillable = [
		'school_id',
		'user_id'
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
