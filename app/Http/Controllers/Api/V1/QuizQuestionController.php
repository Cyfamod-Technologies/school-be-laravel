<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Services\CBT\QuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class QuizQuestionController extends Controller
{
	public function __construct(private QuestionService $questionService)
	{
	}

	private function ensureManager(Request $request): void
	{
		$this->ensurePermission($request, 'cbt.manage');
	}

	private function formatOption(QuizOption $option, bool $includeCorrect = false): array
	{
		$data = [
			'id' => $option->id,
			'question_id' => $option->question_id,
			'option_text' => $option->option_text,
			'order' => $option->order,
			'image_url' => $option->image_url,
		];

		if ($includeCorrect) {
			$data['is_correct'] = $option->is_correct;
		}

		return $data;
	}

	private function formatQuestion(QuizQuestion $question, bool $includeCorrect = false): array
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
			'options' => $question->options->map(function ($option) use ($includeCorrect) {
				return $this->formatOption($option, $includeCorrect);
			}),
		];
	}

	private function clearOtherCorrectOptions(QuizQuestion $question, string $optionId): void
	{
		if (in_array($question->question_type, ['mcq', 'true_false'], true)) {
			$question->options()
				->where('id', '!=', $optionId)
				->update(['is_correct' => false]);
		}
	}

	public function store(Request $request, string $quizId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$this->ensureManager($request);

		$quiz = Quiz::find($quizId);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$validated = $request->validate([
			'question_text' => 'required|string',
			'question_type' => 'required|in:mcq,multiple_select,true_false,short_answer',
			'marks' => 'required|integer|min:1',
			'order' => 'nullable|integer|min:1',
			'image_url' => 'nullable|string',
			'explanation' => 'nullable|string',
			'options' => 'nullable|array',
			'options.*.option_text' => 'required_with:options|string',
			'options.*.is_correct' => 'boolean',
			'options.*.order' => 'nullable|integer|min:1',
			'options.*.image_url' => 'nullable|string',
		]);

		$questionType = $validated['question_type'];
		$options = $validated['options'] ?? [];

		if (in_array($questionType, ['mcq', 'multiple_select', 'true_false'], true) && !empty($options) && count($options) < 2) {
			return response()->json(['message' => 'At least two options are required.'], 422);
		}

		if (in_array($questionType, ['mcq', 'true_false'], true)) {
			$correctCount = collect($options)->where('is_correct', true)->count();
			if ($correctCount > 1) {
				return response()->json(['message' => 'Only one correct option is allowed.'], 422);
			}
		}

		$questionData = Arr::only($validated, [
			'question_text',
			'question_type',
			'marks',
			'order',
			'image_url',
			'explanation',
		]);

		$question = $this->questionService->addQuestion($quiz, $questionData);

		if ($questionType === 'true_false' && empty($options)) {
			$options = [
				[
					'option_text' => 'True',
					'order' => 1,
					'is_correct' => false,
				],
				[
					'option_text' => 'False',
					'order' => 2,
					'is_correct' => false,
				],
			];
		}

		if ($questionType !== 'short_answer') {
			foreach ($options as $optionData) {
				$this->questionService->addOption($question, [
					'option_text' => $optionData['option_text'] ?? '',
					'order' => $optionData['order'] ?? null,
					'is_correct' => $optionData['is_correct'] ?? false,
					'image_url' => $optionData['image_url'] ?? null,
				]);
			}
		}

		$question->load('options');

		return response()->json([
			'message' => 'Question created successfully',
			'data' => $this->formatQuestion($question, true),
		], 201);
	}

	public function update(Request $request, string $quizId, string $questionId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$this->ensureManager($request);

		$quiz = Quiz::find($quizId);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$question = QuizQuestion::find($questionId);

		if (!$question || $question->quiz_id !== $quiz->id) {
			return response()->json(['message' => 'Question not found'], 404);
		}

		$validated = $request->validate([
			'question_text' => 'sometimes|required|string',
			'question_type' => 'sometimes|required|in:mcq,multiple_select,true_false,short_answer',
			'marks' => 'sometimes|required|integer|min:1',
			'order' => 'sometimes|integer|min:1',
			'image_url' => 'nullable|string',
			'explanation' => 'nullable|string',
		]);

		$questionData = Arr::only($validated, [
			'question_text',
			'question_type',
			'marks',
			'order',
			'image_url',
			'explanation',
		]);

		if (!empty($questionData)) {
			$this->questionService->updateQuestion($question, $questionData);
		}

		if (($validated['question_type'] ?? null) === 'short_answer') {
			$question->options()->delete();
		}

		$question->load('options');

		return response()->json([
			'message' => 'Question updated successfully',
			'data' => $this->formatQuestion($question, true),
		]);
	}

	public function destroy(Request $request, string $quizId, string $questionId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$this->ensureManager($request);

		$quiz = Quiz::find($quizId);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$question = QuizQuestion::find($questionId);

		if (!$question || $question->quiz_id !== $quiz->id) {
			return response()->json(['message' => 'Question not found'], 404);
		}

		$this->questionService->deleteQuestion($question);

		return response()->json([
			'message' => 'Question deleted successfully',
		]);
	}

	public function reorder(Request $request, string $quizId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$this->ensureManager($request);

		$quiz = Quiz::find($quizId);

		if (!$quiz) {
			return response()->json(['message' => 'Quiz not found'], 404);
		}

		$validated = $request->validate([
			'order' => 'required|array',
			'order.*' => 'uuid',
		]);

		$this->questionService->reorderQuestions($quiz, $validated['order']);

		return response()->json([
			'message' => 'Question order updated successfully',
		]);
	}

	public function storeOption(Request $request, string $questionId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$this->ensureManager($request);

		$question = QuizQuestion::find($questionId);

		if (!$question) {
			return response()->json(['message' => 'Question not found'], 404);
		}

		if ($question->question_type === 'short_answer') {
			return response()->json(['message' => 'Options are not allowed for short answer questions.'], 422);
		}

		$validated = $request->validate([
			'option_text' => 'required|string',
			'is_correct' => 'boolean',
			'order' => 'nullable|integer|min:1',
			'image_url' => 'nullable|string',
		]);

		if (in_array($question->question_type, ['mcq', 'true_false'], true) && ($validated['is_correct'] ?? false)) {
			$existingCorrect = $question->options()->where('is_correct', true)->exists();
			if ($existingCorrect) {
				$question->options()->update(['is_correct' => false]);
			}
		}

		$option = $this->questionService->addOption($question, $validated);

		if (($validated['is_correct'] ?? false) && in_array($question->question_type, ['mcq', 'true_false'], true)) {
			$this->clearOtherCorrectOptions($question, $option->id);
		}

		return response()->json([
			'message' => 'Option created successfully',
			'data' => $this->formatOption($option, true),
		], 201);
	}

	public function updateOption(Request $request, string $questionId, string $optionId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$this->ensureManager($request);

		$question = QuizQuestion::find($questionId);

		if (!$question) {
			return response()->json(['message' => 'Question not found'], 404);
		}

		$option = QuizOption::find($optionId);

		if (!$option || $option->question_id !== $question->id) {
			return response()->json(['message' => 'Option not found'], 404);
		}

		$validated = $request->validate([
			'option_text' => 'sometimes|required|string',
			'is_correct' => 'boolean',
			'order' => 'nullable|integer|min:1',
			'image_url' => 'nullable|string',
		]);

		$this->questionService->updateOption($option, $validated);

		if (($validated['is_correct'] ?? false) && in_array($question->question_type, ['mcq', 'true_false'], true)) {
			$this->clearOtherCorrectOptions($question, $option->id);
		}

		return response()->json([
			'message' => 'Option updated successfully',
			'data' => $this->formatOption($option->fresh(), true),
		]);
	}

	public function destroyOption(Request $request, string $questionId, string $optionId): JsonResponse
	{
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$this->ensureManager($request);

		$question = QuizQuestion::find($questionId);

		if (!$question) {
			return response()->json(['message' => 'Question not found'], 404);
		}

		$option = QuizOption::find($optionId);

		if (!$option || $option->question_id !== $question->id) {
			return response()->json(['message' => 'Option not found'], 404);
		}

		$this->questionService->deleteOption($option);

		return response()->json([
			'message' => 'Option deleted successfully',
		]);
	}
}
