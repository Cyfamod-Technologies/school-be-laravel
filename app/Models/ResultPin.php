<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ResultPin
 *
 * @property string $id
 * @property string $student_id
 * @property string $pin_code
 * @property string $status
 * @property Carbon|null $expiry_date
 * @property int $use_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Student $student
 *
 * @package App\Models
 */
class ResultPin extends Model
{
	protected $table = 'result_pins';
	public $incrementing = false;

	protected $casts = [
		'expiry_date' => 'datetime',
		'use_count' => 'int'
	];

	protected $fillable = [
		'student_id',
		'pin_code',
		'status',
		'expiry_date',
		'use_count'
	];

	public function student()
	{
		return $this->belongsTo(Student::class);
	}
}
