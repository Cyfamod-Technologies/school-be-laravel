<?php

namespace App\Services\CBT;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AttemptService
{
	/**
	 * Start a new quiz attempt
	 */
	public function startAttempt(Quiz $quiz, User|Student $student): QuizAttempt
	{
		// Check if student already has an active attempt
		$activeAttempt = $quiz->attempts()
			->where('student_id', $student->id)
			->where('status', 'in_progress')
			->first();

		if ($activeAttempt) {
			return $activeAttempt;
		}

		// Create new attempt
		$attempt = new QuizAttempt();
		$attempt->id = Str::uuid();
		$attempt->quiz_id = $quiz->id;
		$attempt->student_id = $student->id;
		$attempt->session_id = $student->current_session_id ?? null;
		$attempt->term_id = $student->current_term_id ?? null;
		$attempt->start_time = Carbon::now();
		$attempt->status = 'in_progress';
		$attempt->ip_address = request()->ip();
		$attempt->user_agent = request()->header('User-Agent');
		$attempt->save();

		return $attempt;
	}

	/**
	 * Get attempt with answers
	 */
	public function getAttempt(QuizAttempt $attempt): array
	{
		return [
			'id' => $attempt->id,
			'quiz_id' => $attempt->quiz_id,
			'student_id' => $attempt->student_id,
			'start_time' => $attempt->start_time,
			'end_time' => $attempt->end_time,
			'status' => $attempt->status,
			'answers' => $attempt->answers->map(function ($answer) {
				return [
					'id' => $answer->id,
					'question_id' => $answer->question_id,
					'selected_option_id' => $answer->selected_option_id,
					'answer_text' => $answer->answer_text,
					'is_correct' => $answer->is_correct,
					'marks_obtained' => $answer->marks_obtained,
					'answered_at' => $answer->answered_at,
				];
			}),
		];
	}

	/**
	 * Get remaining time for attempt
	 */
	public function getRemainingTime(QuizAttempt $attempt): int
	{
		$quiz = $attempt->quiz;
		$elapsed = $attempt->start_time->diffInSeconds(Carbon::now());
		$totalSeconds = $quiz->duration_minutes * 60;
		$remaining = $totalSeconds - $elapsed;

		return max(0, $remaining);
	}

	/**
	 * Check if time has expired
	 */
	public function hasTimeExpired(QuizAttempt $attempt): bool
	{
		return $this->getRemainingTime($attempt) <= 0;
	}

	/**
	 * Submit attempt
	 */
	public function submitAttempt(QuizAttempt $attempt): bool
	{
		$attempt->end_time = Carbon::now();
		$attempt->status = 'submitted';
		$attempt->duration_seconds = $attempt->start_time->diffInSeconds($attempt->end_time);

		return $attempt->save();
	}

	/**
	 * End attempt early
	 */
	public function endAttemptEarly(QuizAttempt $attempt): bool
	{
		return $this->submitAttempt($attempt);
	}

	/**
	 * Get student's attempt history
	 */
	public function getStudentAttemptHistory(User|Student $student): array
	{
		if (method_exists($student, 'quizAttempts')) {
			$attempts = $student->quizAttempts()
				->with(['quiz', 'result'])
				->where('status', '!=', 'in_progress')
				->orderBy('end_time', 'desc')
				->get();
		} else {
			$attempts = QuizAttempt::query()
				->where('student_id', $student->id)
				->with(['quiz', 'result'])
				->where('status', '!=', 'in_progress')
				->orderBy('end_time', 'desc')
				->get();
		}

		return $attempts->map(function ($attempt) {
			return [
				'id' => $attempt->id,
				'quiz_id' => $attempt->quiz_id,
				'quiz_title' => $attempt->quiz->title,
				'start_time' => $attempt->start_time,
				'end_time' => $attempt->end_time,
				'status' => $attempt->status,
				'marks_obtained' => $attempt->result?->marks_obtained,
				'total_marks' => $attempt->result?->total_marks,
				'percentage' => $attempt->result?->percentage,
				'grade' => $attempt->result?->grade,
			];
		})->toArray();
	}
}
