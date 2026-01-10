<?php

namespace App\Services\CBT;

use App\Models\QuizAttempt;
use App\Models\QuizAnswer;
use App\Models\QuizResult;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ScoringService
{
	/**
	 * Calculate score for an attempt
	 */
	public function calculateAttemptScore(QuizAttempt $attempt): QuizResult
	{
		$quiz = $attempt->quiz;
		$answers = $attempt->answers()->with('question')->get();

		$totalQuestions = $quiz->total_questions;
		$attemptedQuestions = $answers->count();
		$correctAnswers = 0;
		$totalMarks = 0;
		$marksObtained = 0;

		// Process each answer
		foreach ($answers as $answer) {
			$question = $answer->question;
			$totalMarks += $question->marks;

			// Evaluate answer correctness
			$isCorrect = $this->evaluateAnswer($question, $answer);
			$marks = $isCorrect ? $question->marks : 0;

			// Update answer
			$answer->update([
				'is_correct' => $isCorrect,
				'marks_obtained' => $marks,
			]);

			if ($isCorrect) {
				$correctAnswers++;
				$marksObtained += $marks;
			}
		}

		// Calculate percentage and grade
		$percentage = $totalMarks > 0 ? ($marksObtained / $totalMarks) * 100 : 0;
		$grade = $this->determineGrade($percentage);
		$status = $percentage >= $quiz->passing_score ? 'pass' : 'fail';

		// Create or update result
		$result = QuizResult::firstOrCreate(
			['attempt_id' => $attempt->id],
			[
				'id' => Str::uuid(),
				'quiz_id' => $quiz->id,
				'student_id' => $attempt->student_id,
				'total_questions' => $totalQuestions,
				'attempted_questions' => $attemptedQuestions,
				'correct_answers' => $correctAnswers,
				'total_marks' => $totalMarks,
				'marks_obtained' => $marksObtained,
				'percentage' => round($percentage, 2),
				'grade' => $grade,
				'status' => $status,
				'submitted_at' => $attempt->end_time ?? Carbon::now(),
				'graded_at' => Carbon::now(),
			]
		);

		// Update attempt status
		$attempt->update(['status' => 'graded']);

		return $result;
	}

	/**
	 * Evaluate if an answer is correct
	 */
	public function evaluateAnswer(QuizQuestion $question, QuizAnswer $answer): bool
	{
		switch ($question->question_type) {
			case 'mcq':
				return $this->evaluateMCQ($question, $answer);
			case 'true_false':
				return $this->evaluateTrueFalse($question, $answer);
			case 'multiple_select':
				return $this->evaluateMultipleSelect($question, $answer);
			case 'short_answer':
				// Short answers need manual grading
				return false;
			default:
				return false;
		}
	}

	/**
	 * Evaluate MCQ answer
	 */
	private function evaluateMCQ(QuizQuestion $question, QuizAnswer $answer): bool
	{
		if (!$answer->selected_option_id) {
			return false;
		}

		$correctOption = $question->options()
			->where('is_correct', true)
			->first();

		return $correctOption && $correctOption->id === $answer->selected_option_id;
	}

	/**
	 * Evaluate True/False answer
	 */
	private function evaluateTrueFalse(QuizQuestion $question, QuizAnswer $answer): bool
	{
		if (!$answer->selected_option_id) {
			return false;
		}

		$correctOption = $question->options()
			->where('is_correct', true)
			->first();

		return $correctOption && $correctOption->id === $answer->selected_option_id;
	}

	/**
	 * Evaluate Multiple Select answer
	 */
	private function evaluateMultipleSelect(QuizQuestion $question, QuizAnswer $answer): bool
	{
		// For multiple select, we might need to check all correct options
		// For now, we'll accept if student selected at least one correct option
		if (!$answer->selected_option_id) {
			return false;
		}

		$selectedOption = $question->options()
			->where('id', $answer->selected_option_id)
			->first();

		return $selectedOption && $selectedOption->is_correct;
	}

	/**
	 * Determine grade based on percentage
	 */
	public function determineGrade(float $percentage): string
	{
		if ($percentage >= 90) {
			return 'A';
		} elseif ($percentage >= 80) {
			return 'B';
		} elseif ($percentage >= 70) {
			return 'C';
		} elseif ($percentage >= 60) {
			return 'D';
		} else {
			return 'F';
		}
	}

	/**
	 * Get quiz statistics
	 */
	public function getQuizStatistics(int $quizId): array
	{
		$results = QuizResult::where('quiz_id', $quizId)->get();

		if ($results->isEmpty()) {
			return [
				'total_attempts' => 0,
				'passed' => 0,
				'failed' => 0,
				'average_score' => 0,
				'average_percentage' => 0,
				'pass_rate' => 0,
				'grade_distribution' => [],
			];
		}

		$totalAttempts = $results->count();
		$passed = $results->where('status', 'pass')->count();
		$failed = $totalAttempts - $passed;

		return [
			'total_attempts' => $totalAttempts,
			'passed' => $passed,
			'failed' => $failed,
			'average_score' => round($results->avg('marks_obtained'), 2),
			'average_percentage' => round($results->avg('percentage'), 2),
			'pass_rate' => round(($passed / $totalAttempts) * 100, 2),
			'grade_distribution' => [
				'A' => $results->where('grade', 'A')->count(),
				'B' => $results->where('grade', 'B')->count(),
				'C' => $results->where('grade', 'C')->count(),
				'D' => $results->where('grade', 'D')->count(),
				'F' => $results->where('grade', 'F')->count(),
			],
		];
	}
}
