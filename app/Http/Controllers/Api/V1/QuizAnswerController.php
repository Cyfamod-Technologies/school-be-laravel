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
}
