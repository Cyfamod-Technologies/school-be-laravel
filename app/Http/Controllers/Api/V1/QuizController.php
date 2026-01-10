<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Services\CBT\QuizService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuizController extends Controller
{
	public function __construct(private QuizService $quizService)
	{
	}

	/**
	 * List quizzes for student
	 */
	public function index(Request $request): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$quizzes = $this->quizService->getStudentQuizzes($user);

		return response()->json([
			'message' => 'Quizzes retrieved successfully',
			'data' => $quizzes->map(function ($quiz) use ($user) {
				return [
					'id' => $quiz->id,
					'title' => $quiz->title,
					'description' => $quiz->description,
					'subject_id' => $quiz->subject_id,
					'class_id' => $quiz->class_id,
					'duration_minutes' => $quiz->duration_minutes,
					'total_questions' => $quiz->total_questions,
					'passing_score' => $quiz->passing_score,
					'status' => $quiz->status,
					'attempted' => $quiz->attempts->isNotEmpty(),
					'created_at' => $quiz->created_at,
					'updated_at' => $quiz->updated_at,
				];
			}),
		]);
	}

	/**
	 * Get quiz details with questions
	 */
	public function show(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$quiz = Quiz::find($id);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		// Check permission
		if (!$this->quizService->canStudentTakeQuiz($user, $quiz)) {
			return response()->json(['message' => 'You do not have access to this quiz'], 403);
		}

		$details = $this->quizService->getQuizDetails($quiz, $user);

		return response()->json([
			'message' => 'Quiz retrieved successfully',
			'data' => $details,
		]);
	}

	/**
	 * Create quiz (admin only)
	 */
	public function store(Request $request): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		// Check permission
		$this->ensurePermission($request, 'cbt.manage');

		$validated = $request->validate([
			'title' => 'required|string|max:255',
			'description' => 'nullable|string',
			'subject_id' => 'nullable|exists:subjects,id',
			'class_id' => 'nullable|exists:school_classes,id',
			'duration_minutes' => 'required|integer|min:1',
			'total_questions' => 'required|integer|min:1',
			'passing_score' => 'required|integer|min:0|max:100',
			'show_answers' => 'boolean',
			'shuffle_questions' => 'boolean',
			'shuffle_options' => 'boolean',
			'allow_review' => 'boolean',
		]);

		$quiz = $this->quizService->createQuiz($validated, $user);

		return response()->json([
			'message' => 'Quiz created successfully',
			'data' => $quiz,
		], 201);
	}

	/**
	 * Update quiz
	 */
	public function update(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		// Check permission
		$this->ensurePermission($request, 'cbt.manage');

		$quiz = Quiz::find($id);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$validated = $request->validate([
			'title' => 'sometimes|string|max:255',
			'description' => 'nullable|string',
			'subject_id' => 'nullable|exists:subjects,id',
			'class_id' => 'nullable|exists:school_classes,id',
			'duration_minutes' => 'sometimes|integer|min:1',
			'total_questions' => 'sometimes|integer|min:1',
			'passing_score' => 'sometimes|integer|min:0|max:100',
			'show_answers' => 'boolean',
			'shuffle_questions' => 'boolean',
			'shuffle_options' => 'boolean',
			'allow_review' => 'boolean',
		]);

		$this->quizService->updateQuiz($quiz, $validated);

		return response()->json([
			'message' => 'Quiz updated successfully',
			'data' => $quiz->fresh(),
		]);
	}

	/**
	 * Delete quiz
	 */
	public function destroy(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		// Check permission
		$this->ensurePermission($request, 'cbt.manage');

		$quiz = Quiz::find($id);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$this->quizService->deleteQuiz($quiz);

		return response()->json([
			'message' => 'Quiz deleted successfully',
		]);
	}

	/**
	 * Publish quiz
	 */
	public function publish(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		// Check permission
		$this->ensurePermission($request, 'cbt.manage');

		$quiz = Quiz::find($id);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$this->quizService->publishQuiz($quiz);

		return response()->json([
			'message' => 'Quiz published successfully',
			'data' => $quiz->fresh(),
		]);
	}

	/**
	 * Close quiz
	 */
	public function close(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		// Check permission
		$this->ensurePermission($request, 'cbt.manage');

		$quiz = Quiz::find($id);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$this->quizService->closeQuiz($quiz);

		return response()->json([
			'message' => 'Quiz closed successfully',
			'data' => $quiz->fresh(),
		]);
	}

	/**
	 * Get quiz statistics
	 */
	public function statistics(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		// Check permission
		$this->ensurePermission($request, 'cbt.manage');

		$quiz = Quiz::find($id);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$stats = $this->quizService->getQuizStatistics($quiz);

		return response()->json([
			'message' => 'Quiz statistics retrieved successfully',
			'data' => $stats,
		]);
	}
}
