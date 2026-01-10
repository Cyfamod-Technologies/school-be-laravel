<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Quiz
 *
 * @property string $id
 * @property string $school_id
 * @property string $title
 * @property string|null $description
 * @property string|null $subject_id
 * @property string|null $class_id
 * @property string $created_by
 * @property int $duration_minutes
 * @property int $total_questions
 * @property int $passing_score
 * @property bool $show_answers
 * @property bool $shuffle_questions
 * @property bool $shuffle_options
 * @property bool $allow_review
 * @property string $status (draft|published|closed)
 * @property Carbon|null $start_time
 * @property Carbon|null $end_time
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property School $school
 * @property Subject|null $subject
 * @property SchoolClass|null $class
 * @property User $creator
 * @property Collection|QuizQuestion[] $questions
 * @property Collection|QuizAttempt[] $attempts
 *
 * @package App\Models
 */
class Quiz extends Model
{
	use SoftDeletes;

	protected $table = 'quizzes';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'school_id',
		'title',
		'description',
		'subject_id',
		'class_id',
		'created_by',
		'duration_minutes',
		'total_questions',
		'passing_score',
		'show_answers',
		'shuffle_questions',
		'shuffle_options',
		'allow_review',
		'status',
		'start_time',
		'end_time',
	];

	protected $casts = [
		'show_answers' => 'boolean',
		'shuffle_questions' => 'boolean',
		'shuffle_options' => 'boolean',
		'allow_review' => 'boolean',
		'start_time' => 'datetime',
		'end_time' => 'datetime',
	];

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function subject()
	{
		return $this->belongsTo(Subject::class);
	}

	public function class()
	{
		return $this->belongsTo(SchoolClass::class, 'class_id');
	}

	public function creator()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function questions()
	{
		return $this->hasMany(QuizQuestion::class, 'quiz_id')->orderBy('order');
	}

	public function attempts()
	{
		return $this->hasMany(QuizAttempt::class, 'quiz_id');
	}
}
