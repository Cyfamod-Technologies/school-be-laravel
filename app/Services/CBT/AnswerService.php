<?php

namespace App\Services\CBT;

use App\Models\QuizAttempt;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AnswerService
{
	/**
	 * Submit an answer to a question
	 */
	public function submitAnswer(QuizAttempt $attempt, QuizQuestion $question, array $data): QuizAnswer
	{
		// Check if answer already exists
		$answer = $attempt->answers()
			->where('question_id', $question->id)
			->first();

		$answerData = [
			'attempt_id' => $attempt->id,
			'question_id' => $question->id,
			'selected_option_id' => $data['selected_option_id'] ?? null,
			'answer_text' => $data['answer_text'] ?? null,
			'answered_at' => Carbon::now(),
		];

		if ($answer) {
			$answer->update($answerData);
			return $answer;
		}

		$answerData['id'] = Str::uuid();
		$answerData['is_correct'] = false;
		$answerData['marks_obtained'] = 0;

		return QuizAnswer::create($answerData);
	}

	/**
	 * Get all answers for an attempt
	 */
	public function getAttemptAnswers(QuizAttempt $attempt): array
	{
		return $attempt->answers->map(function ($answer) {
			return [
				'id' => $answer->id,
				'question_id' => $answer->question_id,
				'selected_option_id' => $answer->selected_option_id,
				'answer_text' => $answer->answer_text,
				'is_correct' => $answer->is_correct,
				'marks_obtained' => $answer->marks_obtained,
				'answered_at' => $answer->answered_at,
			];
		})->toArray();
	}

	/**
	 * Update answer (for review mode)
	 */
	public function updateAnswer(QuizAnswer $answer, array $data): bool
	{
		$data['answered_at'] = Carbon::now();
		return $answer->update($data);
	}
}
