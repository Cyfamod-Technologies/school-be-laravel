<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;

/**
 * Class QuizResult
 *
 * @property string $id
 * @property string $attempt_id
 * @property string $quiz_id
 * @property string $student_id
 * @property int $total_questions
 * @property int $attempted_questions
 * @property int $correct_answers
 * @property int $total_marks
 * @property int $marks_obtained
 * @property float $percentage
 * @property string $grade
 * @property string $status (pass|fail)
 * @property Carbon $submitted_at
 * @property Carbon|null $graded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property QuizAttempt $attempt
 * @property Quiz $quiz
 * @property Student $student
 *
 * @package App\Models
 */
class QuizResult extends Model
{
	protected $table = 'quiz_results';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'attempt_id',
		'quiz_id',
		'student_id',
		'total_questions',
		'attempted_questions',
		'correct_answers',
		'total_marks',
		'marks_obtained',
		'percentage',
		'grade',
		'status',
		'submitted_at',
		'graded_at',
	];

	protected $casts = [
		'submitted_at' => 'datetime',
		'graded_at' => 'datetime',
	];

	public function attempt()
	{
		return $this->belongsTo(QuizAttempt::class);
	}

	public function quiz()
	{
		return $this->belongsTo(Quiz::class);
	}

	public function student()
	{
		return $this->belongsTo(Student::class, 'student_id');
	}
}
