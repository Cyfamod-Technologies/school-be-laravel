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

		$result = QuizResult::with('quiz')->find($id);

		if (!$result) {
			return response()->json(['message' => 'Result not found'], 404);
		}

		// Check if user is the student or admin
		if ($result->student_id !== $user->id) {
			$this->ensurePermission($request, 'cbt.view_results');
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
				'quiz' => $result->quiz
					? [
						'id' => $result->quiz->id,
						'title' => $result->quiz->title,
						'show_score' => $result->quiz->show_score,
						'show_answers' => $result->quiz->show_answers,
						'allow_review' => $result->quiz->allow_review,
					]
					: null,
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
		$this->ensurePermission($request, 'cbt.view_results');

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
				$studentName = $this->formatStudentName($result->student);

				return [
					'id' => $result->id,
					'attempt_id' => $result->attempt_id,
					'quiz_id' => $result->quiz_id,
					'student_id' => $result->student_id,
					'student_name' => $studentName !== '' ? $studentName : null,
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
		$this->ensurePermission($request, 'cbt.view_results');

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

	/**
	 * Review answers for a result (student review)
	 */
	public function review(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (! $user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$result = QuizResult::with(['attempt.quiz.questions.options', 'attempt.answers'])->find($id);

		if (! $result) {
			return response()->json(['message' => 'Result not found'], 404);
		}

		$attempt = $result->attempt;

		if (! $attempt) {
			return response()->json(['message' => 'Attempt not found'], 404);
		}

		$quiz = $attempt->quiz;

		if (! $quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		if ($result->student_id !== $user->id) {
			$this->ensurePermission($request, 'cbt.view_results');
		} elseif (! $quiz->allow_review) {
			return response()->json(['message' => 'Quiz does not allow review'], 403);
		}

		$showAnswers = (bool) $quiz->show_answers;
		$answersByQuestion = $attempt->answers->keyBy('question_id');

		$questions = $quiz->questions->map(function ($question) use ($answersByQuestion, $showAnswers) {
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

			$payload = [
				'id' => $question->id,
				'question_text' => $question->question_text,
				'question_type' => $question->question_type,
				'marks' => $question->marks,
				'order' => $question->order,
				'image_url' => $question->image_url,
				'explanation' => $question->explanation,
				'options' => $question->options->map(function ($option) use ($showAnswers) {
					$optionPayload = [
						'id' => $option->id,
						'option_text' => $option->option_text,
						'order' => $option->order,
						'image_url' => $option->image_url,
					];

					if ($showAnswers) {
						$optionPayload['is_correct'] = $option->is_correct;
					}

					return $optionPayload;
				}),
				'answer' => $answer
					? [
						'selected_option_id' => $answer->selected_option_id,
						'selected_option_ids' => $selectedOptionIds,
						'answer_text' => $answer->answer_text,
					]
					: null,
			];

			if ($showAnswers && $question->question_type === 'short_answer') {
				$payload['accepted_answers'] = $question->short_answer_answers ?? [];
			}

			if ($showAnswers && $answer) {
				$payload['answer']['is_correct'] = $answer->is_correct;
			}

			return $payload;
		});

		return response()->json([
			'message' => 'Quiz review retrieved successfully',
			'data' => [
				'attempt' => [
					'id' => $attempt->id,
					'quiz_id' => $attempt->quiz_id,
					'status' => $attempt->status,
					'start_time' => $attempt->start_time,
					'end_time' => $attempt->end_time,
				],
				'quiz' => [
					'id' => $quiz->id,
					'title' => $quiz->title,
					'show_answers' => $quiz->show_answers,
					'allow_review' => $quiz->allow_review,
				],
				'questions' => $questions,
			],
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
