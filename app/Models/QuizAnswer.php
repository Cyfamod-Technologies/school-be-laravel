<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class QuizAnswer
 *
 * @property string $id
 * @property string $attempt_id
 * @property string $question_id
 * @property string|null $selected_option_id
 * @property string|null $answer_text
 * @property bool $is_correct
 * @property int $marks_obtained
 * @property int|null $time_spent_seconds
 * @property Carbon $answered_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property QuizAttempt $attempt
 * @property QuizQuestion $question
 * @property QuizOption|null $selectedOption
 *
 * @package App\Models
 */
class QuizAnswer extends Model
{
	protected $table = 'quiz_answers';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'attempt_id',
		'question_id',
		'selected_option_id',
		'answer_text',
		'is_correct',
		'marks_obtained',
		'time_spent_seconds',
		'answered_at',
	];

	protected $casts = [
		'is_correct' => 'boolean',
		'answered_at' => 'datetime',
	];

	public function attempt()
	{
		return $this->belongsTo(QuizAttempt::class);
	}

	public function question()
	{
		return $this->belongsTo(QuizQuestion::class);
	}

	public function selectedOption()
	{
		return $this->belongsTo(QuizOption::class, 'selected_option_id');
	}
}
