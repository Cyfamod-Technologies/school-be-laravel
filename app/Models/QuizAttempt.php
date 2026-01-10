<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class QuizAttempt
 *
 * @property string $id
 * @property string $quiz_id
 * @property string $student_id
 * @property string|null $session_id
 * @property string|null $term_id
 * @property Carbon $start_time
 * @property Carbon|null $end_time
 * @property int|null $duration_seconds
 * @property string $status (in_progress|submitted|graded)
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Quiz $quiz
 * @property User $student
 * @property Session|null $session
 * @property Term|null $term
 * @property Collection|QuizAnswer[] $answers
 * @property QuizResult|null $result
 *
 * @package App\Models
 */
class QuizAttempt extends Model
{
	protected $table = 'quiz_attempts';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'quiz_id',
		'student_id',
		'session_id',
		'term_id',
		'start_time',
		'end_time',
		'duration_seconds',
		'status',
		'ip_address',
		'user_agent',
	];

	protected $casts = [
		'start_time' => 'datetime',
		'end_time' => 'datetime',
	];

	public function quiz()
	{
		return $this->belongsTo(Quiz::class);
	}

	public function student()
	{
		return $this->belongsTo(User::class, 'student_id');
	}

	public function session()
	{
		return $this->belongsTo(Session::class);
	}

	public function term()
	{
		return $this->belongsTo(Term::class);
	}

	public function answers()
	{
		return $this->hasMany(QuizAnswer::class, 'attempt_id');
	}

	public function result()
	{
		return $this->hasOne(QuizResult::class, 'attempt_id');
	}
}
