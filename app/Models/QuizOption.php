<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class QuizOption
 *
 * @property string $id
 * @property string $question_id
 * @property string $option_text
 * @property int $order
 * @property bool $is_correct
 * @property string|null $image_url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property QuizQuestion $question
 *
 * @package App\Models
 */
class QuizOption extends Model
{
	protected $table = 'quiz_options';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'question_id',
		'option_text',
		'order',
		'is_correct',
		'image_url',
	];

	protected $casts = [
		'is_correct' => 'boolean',
	];

	public function question()
	{
		return $this->belongsTo(QuizQuestion::class);
	}
}
