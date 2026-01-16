<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Student;
use App\Services\CBT\QuizService;
use App\Services\CBT\QuestionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class QuizController extends Controller
{
	public function __construct(
		private QuizService $quizService,
		private QuestionService $questionService
	)
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

		if ($user instanceof Student) {
			$quizzes = $this->quizService->getStudentQuizzes($user);

			return response()->json([
				'message' => 'Quizzes retrieved successfully',
				'data' => $quizzes->map(function ($quiz) {
					return [
						'id' => $quiz->id,
						'title' => $quiz->title,
						'description' => $quiz->description,
						'subject_id' => $quiz->subject_id,
						'subject_name' => $quiz->subject?->name,
						'class_id' => $quiz->class_id,
						'duration_minutes' => $quiz->duration_minutes,
						'total_questions' => $quiz->total_questions,
						'passing_score' => $quiz->passing_score,
						'show_score' => $quiz->show_score,
						'allow_multiple_attempts' => $quiz->allow_multiple_attempts,
						'status' => $quiz->status,
						'attempted' => $quiz->attempts->isNotEmpty(),
						'created_at' => $quiz->created_at,
						'updated_at' => $quiz->updated_at,
					];
				}),
			]);
		}

		$role = strtolower((string) ($user->role ?? ''));
		$isAdminRole = in_array($role, ['admin', 'super_admin'], true);
		$hasAdminSpatieRole = $user->hasAnyRole(['admin', 'super_admin']);
		$canManage = $isAdminRole || $hasAdminSpatieRole || $user->can('cbt.manage') || $user->can('cbt.view');

		if ($canManage) {
			$query = Quiz::query()->with('subject:id,name')->orderBy('created_at', 'desc');
			if ($user->school_id) {
				$query->where('school_id', $user->school_id);
			} else {
				$query->where('created_by', $user->id);
			}

			$quizzes = $query->get();

			return response()->json([
				'message' => 'Quizzes retrieved successfully',
				'data' => $quizzes->map(function ($quiz) {
					return [
						'id' => $quiz->id,
						'title' => $quiz->title,
						'description' => $quiz->description,
						'subject_id' => $quiz->subject_id,
						'subject_name' => $quiz->subject?->name,
						'class_id' => $quiz->class_id,
						'duration_minutes' => $quiz->duration_minutes,
						'total_questions' => $quiz->total_questions,
						'passing_score' => $quiz->passing_score,
						'show_score' => $quiz->show_score,
						'allow_multiple_attempts' => $quiz->allow_multiple_attempts,
						'status' => $quiz->status,
						'created_at' => $quiz->created_at,
						'updated_at' => $quiz->updated_at,
					];
				}),
			]);
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
					'subject_name' => $quiz->subject?->name,
					'class_id' => $quiz->class_id,
					'duration_minutes' => $quiz->duration_minutes,
					'total_questions' => $quiz->total_questions,
					'passing_score' => $quiz->passing_score,
					'show_score' => $quiz->show_score,
					'allow_multiple_attempts' => $quiz->allow_multiple_attempts,
					'status' => $quiz->status,
					'attempted' => $quiz->attempts->isNotEmpty(),
					'created_at' => $quiz->created_at,
					'updated_at' => $quiz->updated_at,
				];
			}),
		]);
	}

	/**
	 * List published quizzes for public selection (no auth required)
	 */
	public function publicIndex(Request $request): JsonResponse
	{
		$now = Carbon::now();
		$query = Quiz::query()
			->with('subject:id,name')
			->where('status', 'published')
			->where(function ($builder) use ($now) {
				$builder->whereNull('start_time')->orWhere('start_time', '<=', $now);
			})
			->where(function ($builder) use ($now) {
				$builder->whereNull('end_time')->orWhere('end_time', '>=', $now);
			})
			->orderBy('created_at', 'desc');

		if ($request->filled('subject_id')) {
			$query->where('subject_id', $request->string('subject_id'));
		}

		$quizzes = $query->get();

		return response()->json([
			'message' => 'Quizzes retrieved successfully',
			'data' => $quizzes->map(function ($quiz) {
				return [
					'id' => $quiz->id,
					'title' => $quiz->title,
					'description' => $quiz->description,
					'subject_id' => $quiz->subject_id,
					'subject_name' => $quiz->subject?->name,
					'duration_minutes' => $quiz->duration_minutes,
					'total_questions' => $quiz->total_questions,
					'passing_score' => $quiz->passing_score,
					'show_score' => $quiz->show_score,
					'allow_multiple_attempts' => $quiz->allow_multiple_attempts,
					'status' => $quiz->status,
					'start_time' => $quiz->start_time,
					'end_time' => $quiz->end_time,
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

		if ($user instanceof Student) {
			if (!$this->quizService->canStudentTakeQuiz($user, $quiz)) {
				if (! $quiz->allow_multiple_attempts && $this->quizService->hasStudentAttempted($user, $quiz)) {
					return response()->json(['message' => 'This quiz can only be taken once.'], 403);
				}

				return response()->json(['message' => 'You do not have access to this quiz'], 403);
			}

			$details = $this->quizService->getQuizDetails($quiz, $user);

			return response()->json([
				'message' => 'Quiz retrieved successfully',
				'data' => $details,
			]);
		}

		$role = strtolower((string) ($user->role ?? ''));
		$isAdminRole = in_array($role, ['admin', 'super_admin'], true);
		$hasAdminSpatieRole = $user->hasAnyRole(['admin', 'super_admin']);
		$canManage = $isAdminRole || $hasAdminSpatieRole || $user->can('cbt.manage') || $user->can('cbt.view');

		// Check permission for students (admins can access drafts)
		if (!$canManage && !$this->quizService->canStudentTakeQuiz($user, $quiz)) {
			return response()->json(['message' => 'You do not have access to this quiz'], 403);
		}

		$details = $this->quizService->getQuizDetails($quiz, $user);

		return response()->json([
			'message' => 'Quiz retrieved successfully',
			'data' => $details,
		]);
	}

	/**
	 * Get quiz questions
	 */
	public function getQuestions(Request $request, string $id): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$quiz = Quiz::find($id);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		if ($user instanceof Student) {
			if (!$this->quizService->canStudentTakeQuiz($user, $quiz)) {
				if (! $quiz->allow_multiple_attempts && $this->quizService->hasStudentAttempted($user, $quiz)) {
					return response()->json(['message' => 'This quiz can only be taken once.'], 403);
				}

				return response()->json(['message' => 'You do not have access to this quiz'], 403);
			}

			$questions = $this->questionService->getShuffledQuestions($quiz);
			$questions->load('options');

			return response()->json([
				'message' => 'Questions retrieved successfully',
				'data' => $questions->map(function ($question) {
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
								'question_id' => $option->question_id,
								'option_text' => $option->option_text,
								'order' => $option->order,
								'image_url' => $option->image_url,
							];
						}),
					];
				}),
			]);
		}

		$role = strtolower((string) ($user->role ?? ''));
		$isAdminRole = in_array($role, ['admin', 'super_admin'], true);
		$hasAdminSpatieRole = $user->hasAnyRole(['admin', 'super_admin']);
		$canManage = $isAdminRole || $hasAdminSpatieRole || $user->can('cbt.manage');
		$canView = $canManage || $user->can('cbt.view');
		$includeCorrect = $request->boolean('include_correct') && $canManage;

		if ($canView) {
			$questions = $quiz->questions()->with('options')->orderBy('order')->get();

			return response()->json([
				'message' => 'Questions retrieved successfully',
				'data' => $questions->map(function ($question) use ($includeCorrect) {
					$payload = [
						'id' => $question->id,
						'quiz_id' => $question->quiz_id,
						'question_text' => $question->question_text,
						'question_type' => $question->question_type,
						'marks' => $question->marks,
						'order' => $question->order,
						'image_url' => $question->image_url,
						'explanation' => $question->explanation,
						'options' => $question->options->map(function ($option) use ($includeCorrect) {
							$payload = [
								'id' => $option->id,
								'question_id' => $option->question_id,
								'option_text' => $option->option_text,
								'order' => $option->order,
								'image_url' => $option->image_url,
							];

							if ($includeCorrect) {
								$payload['is_correct'] = $option->is_correct;
							}

							return $payload;
						}),
					];

					if ($includeCorrect) {
						$payload['short_answer_answers'] = $question->short_answer_answers ?? [];
						$payload['short_answer_keywords'] = $question->short_answer_keywords ?? [];
						$payload['short_answer_match'] = $question->short_answer_match ?? 'exact';
					}

					return $payload;
				}),
			]);
		}

		// Student access
		if (!$this->quizService->canStudentTakeQuiz($user, $quiz)) {
			return response()->json(['message' => 'You do not have access to this quiz'], 403);
		}

		$questions = $this->questionService->getShuffledQuestions($quiz);
		$questions->load('options');

		return response()->json([
			'message' => 'Questions retrieved successfully',
			'data' => $questions->map(function ($question) {
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
							'question_id' => $option->question_id,
							'option_text' => $option->option_text,
							'order' => $option->order,
							'image_url' => $option->image_url,
						];
					}),
				];
			}),
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
			'class_id' => 'nullable|exists:classes,id',
			'duration_minutes' => 'required|integer|min:1',
			'total_questions' => 'required|integer|min:1',
			'passing_score' => 'required|integer|min:0|max:100',
			'show_answers' => 'boolean',
			'show_score' => 'boolean',
			'shuffle_questions' => 'boolean',
			'shuffle_options' => 'boolean',
			'allow_review' => 'boolean',
			'allow_multiple_attempts' => 'boolean',
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
			'class_id' => 'nullable|exists:classes,id',
			'duration_minutes' => 'sometimes|integer|min:1',
			'total_questions' => 'sometimes|integer|min:1',
			'passing_score' => 'sometimes|integer|min:0|max:100',
			'show_answers' => 'boolean',
			'show_score' => 'boolean',
			'shuffle_questions' => 'boolean',
			'shuffle_options' => 'boolean',
			'allow_review' => 'boolean',
			'allow_multiple_attempts' => 'boolean',
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
	 * Unpublish quiz
	 */
	public function unpublish(Request $request, string $id): JsonResponse
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

		$this->quizService->unpublishQuiz($quiz);

		return response()->json([
			'message' => 'Quiz unpublished successfully',
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
