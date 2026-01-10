<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Services\CBT\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuizResultController extends Controller
{
	public function __construct(private ScoringService $scoringService)
	{
	}

	/**
	 * Get result details
	 */
	public function show(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$result = QuizResult::find($id);

		if (!$result) {
			return response()->json(['message' => 'Result not found'], 404);
		}

		// Check if user is the student or admin
		if ($result->student_id !== $user->id) {
			$this->ensurePermission($request, 'cbt.manage');
		}

		return response()->json([
			'message' => 'Result retrieved successfully',
			'data' => [
				'id' => $result->id,
				'attempt_id' => $result->attempt_id,
				'quiz_id' => $result->quiz_id,
				'student_id' => $result->student_id,
				'total_questions' => $result->total_questions,
				'attempted_questions' => $result->attempted_questions,
				'correct_answers' => $result->correct_answers,
				'total_marks' => $result->total_marks,
				'marks_obtained' => $result->marks_obtained,
				'percentage' => $result->percentage,
				'grade' => $result->grade,
				'status' => $result->status,
				'submitted_at' => $result->submitted_at,
				'graded_at' => $result->graded_at,
			],
		]);
	}

	/**
	 * Get results by quiz (admin only)
	 */
	public function byQuiz(Request $request, string $quizId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		// Check permission
		$this->ensurePermission($request, 'cbt.manage');

		$quiz = Quiz::find($quizId);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$results = QuizResult::where('quiz_id', $quizId)
			->with(['student', 'attempt'])
			->get();

		return response()->json([
			'message' => 'Results retrieved successfully',
			'data' => $results->map(function ($result) {
				return [
					'id' => $result->id,
					'attempt_id' => $result->attempt_id,
					'quiz_id' => $result->quiz_id,
					'student_id' => $result->student_id,
					'student_name' => $result->student?->name,
					'total_questions' => $result->total_questions,
					'attempted_questions' => $result->attempted_questions,
					'correct_answers' => $result->correct_answers,
					'total_marks' => $result->total_marks,
					'marks_obtained' => $result->marks_obtained,
					'percentage' => $result->percentage,
					'grade' => $result->grade,
					'status' => $result->status,
					'submitted_at' => $result->submitted_at,
					'graded_at' => $result->graded_at,
				];
			}),
		]);
	}

	/**
	 * Get quiz statistics
	 */
	public function statistics(Request $request, string $quizId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		// Check permission
		$this->ensurePermission($request, 'cbt.manage');

		$quiz = Quiz::find($quizId);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$stats = $this->scoringService->getQuizStatistics($quizId);

		return response()->json([
			'message' => 'Quiz statistics retrieved successfully',
			'data' => $stats,
		]);
	}

	/**
	 * Get student's CBT report
	 */
	public function studentReport(Request $request): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$results = QuizResult::where('student_id', $user->id)
			->with(['quiz', 'attempt'])
			->orderBy('submitted_at', 'desc')
			->get();

		return response()->json([
			'message' => 'Student report retrieved successfully',
			'data' => [
				'total_quizzes_taken' => $results->count(),
				'passed' => $results->where('status', 'pass')->count(),
				'failed' => $results->where('status', 'fail')->count(),
				'average_score' => round($results->avg('marks_obtained'), 2),
				'average_percentage' => round($results->avg('percentage'), 2),
				'results' => $results->map(function ($result) {
					return [
						'id' => $result->id,
						'quiz_title' => $result->quiz->title,
						'marks_obtained' => $result->marks_obtained,
						'total_marks' => $result->total_marks,
						'percentage' => $result->percentage,
						'grade' => $result->grade,
						'status' => $result->status,
						'submitted_at' => $result->submitted_at,
					];
				}),
			],
		]);
	}
}
