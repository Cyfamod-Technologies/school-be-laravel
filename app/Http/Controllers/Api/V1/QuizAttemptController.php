<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\CBT\AttemptService;
use App\Services\CBT\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuizAttemptController extends Controller
{
	public function __construct(
		private AttemptService $attemptService,
		private ScoringService $scoringService,
	) {
	}

	/**
	 * Start a quiz attempt
	 */
	public function store(Request $request): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$validated = $request->validate([
			'quiz_id' => 'required|exists:quizzes,id',
		]);

		$quiz = Quiz::find($validated['quiz_id']);

		// Start attempt
		$attempt = $this->attemptService->startAttempt($quiz, $user);

		return response()->json([
			'message' => 'Quiz attempt started successfully',
			'data' => [
				'id' => $attempt->id,
				'quiz_id' => $attempt->quiz_id,
				'student_id' => $attempt->student_id,
				'start_time' => $attempt->start_time,
				'status' => $attempt->status,
			],
		], 201);
	}

	/**
	 * Get attempt details
	 */
	public function show(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$attempt = QuizAttempt::find($id);

		if (!$attempt) {
			return response()->json(['message' => 'Attempt not found'], 404);
		}

		// Check if user is the student
		if ($attempt->student_id !== $user->id) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		$details = $this->attemptService->getAttempt($attempt);

		return response()->json([
			'message' => 'Attempt retrieved successfully',
			'data' => $details,
		]);
	}

	/**
	 * Get remaining time for attempt
	 */
	public function timer(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$attempt = QuizAttempt::find($id);

		if (!$attempt) {
			return response()->json(['message' => 'Attempt not found'], 404);
		}

		// Check if user is the student
		if ($attempt->student_id !== $user->id) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		$remaining = $this->attemptService->getRemainingTime($attempt);
		$hasExpired = $remaining <= 0;

		return response()->json([
			'message' => 'Remaining time retrieved successfully',
			'data' => [
				'remaining_seconds' => max(0, $remaining),
				'has_expired' => $hasExpired,
			],
		]);
	}

	/**
	 * Submit quiz attempt
	 */
	public function submit(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$attempt = QuizAttempt::find($id);

		if (!$attempt) {
			return response()->json(['message' => 'Attempt not found'], 404);
		}

		// Check if user is the student
		if ($attempt->student_id !== $user->id) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		// Check if already submitted
		if ($attempt->status !== 'in_progress') {
			return response()->json(['message' => 'Attempt already submitted'], 400);
		}

		// Submit attempt
		$this->attemptService->submitAttempt($attempt);

		// Calculate score
		$result = $this->scoringService->calculateAttemptScore($attempt);

		return response()->json([
			'message' => 'Quiz submitted successfully',
			'data' => [
				'attempt_id' => $attempt->id,
				'result_id' => $result->id,
				'marks_obtained' => $result->marks_obtained,
				'total_marks' => $result->total_marks,
				'percentage' => $result->percentage,
				'grade' => $result->grade,
				'status' => $result->status,
			],
		]);
	}

	/**
	 * End attempt early
	 */
	public function end(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$attempt = QuizAttempt::find($id);

		if (!$attempt) {
			return response()->json(['message' => 'Attempt not found'], 404);
		}

		// Check if user is the student
		if ($attempt->student_id !== $user->id) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		$this->attemptService->endAttemptEarly($attempt);

		// Calculate score
		$result = $this->scoringService->calculateAttemptScore($attempt);

		return response()->json([
			'message' => 'Quiz ended early',
			'data' => [
				'attempt_id' => $attempt->id,
				'result_id' => $result->id,
			],
		]);
	}

	/**
	 * Get student's attempt history
	 */
	public function history(Request $request): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$history = $this->attemptService->getStudentAttemptHistory($user);

		return response()->json([
			'message' => 'Attempt history retrieved successfully',
			'data' => $history,
		]);
	}
}
