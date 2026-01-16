<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Services\CBT\AnswerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuizAnswerController extends Controller
{
	public function __construct(private AnswerService $answerService)
	{
	}

	/**
	 * Submit an answer to a question
	 */
	public function store(Request $request): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$validated = $request->validate([
			'attempt_id' => 'required|exists:quiz_attempts,id',
			'question_id' => 'required|exists:quiz_questions,id',
			'selected_option_id' => 'nullable|exists:quiz_options,id',
			'answer_text' => 'nullable|string',
		]);

		$attempt = QuizAttempt::find($validated['attempt_id']);

		// Check if user is the student
		if ($attempt->student_id !== $user->id) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		// Check if attempt is still in progress
		if ($attempt->status !== 'in_progress') {
			return response()->json(['message' => 'Attempt is not in progress'], 400);
		}

		$question = QuizQuestion::find($validated['question_id']);

		// Submit answer
		$answer = $this->answerService->submitAnswer($attempt, $question, $validated);

		return response()->json([
			'message' => 'Answer submitted successfully',
			'data' => [
				'id' => $answer->id,
				'question_id' => $answer->question_id,
				'selected_option_id' => $answer->selected_option_id,
				'answer_text' => $answer->answer_text,
				'answered_at' => $answer->answered_at,
			],
		], 201);
	}

	/**
	 * Get all answers for an attempt
	 */
	public function byAttempt(Request $request, string $attemptId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$attempt = QuizAttempt::find($attemptId);

		if (!$attempt) {
			return response()->json(['message' => 'Attempt not found'], 404);
		}

		// Check if user is the student or admin
		if ($attempt->student_id !== $user->id) {
			// Check if user has permission to view answers
			$this->ensurePermission($request, 'cbt.manage');
		}

		$answers = $this->answerService->getAttemptAnswers($attempt);

		return response()->json([
			'message' => 'Answers retrieved successfully',
			'data' => $answers,
		]);
	}

	/**
	 * Get attempt answers with question details (admin only)
	 */
	public function byAttemptDetailed(Request $request, string $attemptId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$this->ensurePermission($request, 'cbt.manage');

		$attempt = QuizAttempt::with(['quiz.questions.options', 'student', 'result', 'answers'])
			->find($attemptId);

		if (!$attempt) {
			return response()->json(['message' => 'Attempt not found'], 404);
		}

		$answersByQuestion = $attempt->answers->keyBy('question_id');

		$questions = $attempt->quiz->questions->map(function ($question) use ($answersByQuestion) {
			$answer = $answersByQuestion->get($question->id);
			$selectedOptionIds = [];

			if ($answer?->answer_text) {
				$decoded = json_decode($answer->answer_text, true);
				if (is_array($decoded)) {
					$selectedOptionIds = array_values(array_filter($decoded, 'is_string'));
				}
			}

			if (empty($selectedOptionIds) && $answer?->selected_option_id) {
				$selectedOptionIds = [$answer->selected_option_id];
			}

			return [
				'id' => $question->id,
				'question_text' => $question->question_text,
				'question_type' => $question->question_type,
				'marks' => $question->marks,
				'order' => $question->order,
				'options' => $question->options->map(function ($option) {
					return [
						'id' => $option->id,
						'option_text' => $option->option_text,
						'order' => $option->order,
						'is_correct' => $option->is_correct,
					];
				}),
				'answer' => $answer
					? [
						'selected_option_id' => $answer->selected_option_id,
						'selected_option_ids' => $selectedOptionIds,
						'answer_text' => $answer->answer_text,
						'is_correct' => $answer->is_correct,
						'marks_obtained' => $answer->marks_obtained,
					]
					: null,
			];
		});

		$studentName = $this->formatStudentName($attempt->student);

		return response()->json([
			'message' => 'Attempt answers retrieved successfully',
			'data' => [
				'attempt' => [
					'id' => $attempt->id,
					'quiz_id' => $attempt->quiz_id,
					'student_id' => $attempt->student_id,
					'student_name' => $studentName !== '' ? $studentName : null,
					'status' => $attempt->status,
					'start_time' => $attempt->start_time,
					'end_time' => $attempt->end_time,
					'result' => $attempt->result
						? [
							'id' => $attempt->result->id,
							'percentage' => $attempt->result->percentage,
							'grade' => $attempt->result->grade,
							'status' => $attempt->result->status,
							'marks_obtained' => $attempt->result->marks_obtained,
							'total_marks' => $attempt->result->total_marks,
							'submitted_at' => $attempt->result->submitted_at,
						]
						: null,
				],
				'quiz' => [
					'id' => $attempt->quiz->id,
					'title' => $attempt->quiz->title,
				],
				'questions' => $questions,
			],
		]);
	}

	/**
	 * Update answer (for review mode)
	 */
	public function update(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$answer = QuizAnswer::find($id);

		if (!$answer) {
			return response()->json(['message' => 'Answer not found'], 404);
		}

		$attempt = $answer->attempt;

		// Check if user is the student
		if ($attempt->student_id !== $user->id) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		// Check if quiz allows review
		if (!$attempt->quiz->allow_review) {
			return response()->json(['message' => 'Quiz does not allow review'], 400);
		}

		$validated = $request->validate([
			'selected_option_id' => 'nullable|exists:quiz_options,id',
			'answer_text' => 'nullable|string',
		]);

		$this->answerService->updateAnswer($answer, $validated);

		return response()->json([
			'message' => 'Answer updated successfully',
			'data' => $answer->fresh(),
		]);
	}

	private function formatStudentName(?\App\Models\Student $student): string
	{
		if (! $student) {
			return '';
		}

		return trim(collect([$student->first_name, $student->middle_name, $student->last_name])
			->filter()
			->implode(' '));
	}
}
