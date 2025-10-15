<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PerformanceReport
 *
 * @property string $id
 * @property string $student_id
 * @property string $report_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property Student $student
 *
 * @package App\Models
 */
class PerformanceReport extends Model
{
	protected $table = 'performance_reports';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $fillable = [
		'student_id',
		'report_data'
	];

	public function student()
	{
		return $this->belongsTo(Student::class);
	}
}
