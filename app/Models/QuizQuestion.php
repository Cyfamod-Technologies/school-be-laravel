<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class QuizQuestion
 *
 * @property string $id
 * @property string $quiz_id
 * @property string $question_text
 * @property string $question_type (mcq|multiple_select|true_false|short_answer)
 * @property int $marks
 * @property int $order
 * @property string|null $image_url
 * @property string|null $explanation
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Quiz $quiz
 * @property Collection|QuizOption[] $options
 * @property Collection|QuizAnswer[] $answers
 *
 * @package App\Models
 */
class QuizQuestion extends Model
{
	protected $table = 'quiz_questions';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'quiz_id',
		'question_text',
		'question_type',
		'marks',
		'order',
		'image_url',
		'explanation',
	];

	public function quiz()
	{
		return $this->belongsTo(Quiz::class);
	}

	public function options()
	{
		return $this->hasMany(QuizOption::class, 'question_id')->orderBy('order');
	}

	public function answers()
	{
		return $this->hasMany(QuizAnswer::class, 'question_id');
	}
}
