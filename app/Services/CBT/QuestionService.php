<?php

namespace App\Services\CBT;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizOption;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;

class QuestionService
{
	/**
	 * Add question to quiz
	 */
	public function addQuestion(Quiz $quiz, array $data): QuizQuestion
	{
		$data['id'] = Str::uuid();
		$data['quiz_id'] = $quiz->id;

		// Get next order if not provided
		if (!isset($data['order'])) {
			$data['order'] = $quiz->questions()->max('order') + 1 ?? 1;
		}

		return QuizQuestion::create($data);
	}

	/**
	 * Update question
	 */
	public function updateQuestion(QuizQuestion $question, array $data): bool
	{
		return $question->update($data);
	}

	/**
	 * Delete question
	 */
	public function deleteQuestion(QuizQuestion $question): bool
	{
		// Delete associated options
		$question->options()->delete();
		
		// Delete associated answers
		$question->answers()->delete();

		return $question->delete();
	}

	/**
	 * Add option to question
	 */
	public function addOption(QuizQuestion $question, array $data): QuizOption
	{
		$data['id'] = Str::uuid();
		$data['question_id'] = $question->id;

		// Get next order if not provided
		if (!isset($data['order'])) {
			$data['order'] = $question->options()->max('order') + 1 ?? 1;
		}

		return QuizOption::create($data);
	}

	/**
	 * Update option
	 */
	public function updateOption(QuizOption $option, array $data): bool
	{
		return $option->update($data);
	}

	/**
	 * Delete option
	 */
	public function deleteOption(QuizOption $option): bool
	{
		return $option->delete();
	}

	/**
	 * Reorder questions
	 */
	public function reorderQuestions(Quiz $quiz, array $order): bool
	{
		foreach ($order as $index => $questionId) {
			QuizQuestion::where('id', $questionId)
				->where('quiz_id', $quiz->id)
				->update(['order' => $index + 1]);
		}

		return true;
	}

	/**
	 * Get shuffled questions (if shuffle enabled)
	 */
	public function getShuffledQuestions(Quiz $quiz): Collection
	{
		$questions = $quiz->questions;

		if ($quiz->shuffle_questions) {
			$questions = $questions->shuffle();
		}

		// Shuffle options if needed
		if ($quiz->shuffle_options) {
			$questions = $questions->map(function ($question) {
				if (in_array($question->question_type, ['mcq', 'multiple_select'])) {
					$question->setRelation('options', $question->options->shuffle());
				}
				return $question;
			});
		}

		return $questions;
	}

	/**
	 * Get question with options
	 */
	public function getQuestionWithOptions(QuizQuestion $question): array
	{
		return [
			'id' => $question->id,
			'quiz_id' => $question->quiz_id,
			'question_text' => $question->question_text,
			'question_type' => $question->question_type,
			'marks' => $question->marks,
			'order' => $question->order,
			'image_url' => $question->image_url,
			'explanation' => $question->explanation,
			'options' => $question->options->map(function ($option) {
				return [
					'id' => $option->id,
					'option_text' => $option->option_text,
					'order' => $option->order,
					'image_url' => $option->image_url,
				];
			}),
		];
	}
}
