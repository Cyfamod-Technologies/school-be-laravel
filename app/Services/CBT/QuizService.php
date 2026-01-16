<?php

namespace App\Services\CBT;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

class QuizService
{
	/**
	 * Get all quizzes for a student
	 */
	public function getStudentQuizzes(User|Student $student): Collection
	{
		$studentClassIds = $this->resolveStudentClassIds($student);

		$query = Quiz::where('status', 'published');

		if (!empty($student->school_id)) {
			$query->where('school_id', $student->school_id);
		}

		return $query->where(function ($query) use ($studentClassIds) {
				// Quiz assigned to student's class
				if ($studentClassIds->count() > 0) {
					$query->whereIn('class_id', $studentClassIds)
						// Or quiz has no specific class (available to all)
						->orWhereNull('class_id');
				} else {
					// If student has no class enrollments, only show quizzes available to all
					$query->whereNull('class_id');
				}
			})
			->with(['subject:id,name', 'questions', 'attempts' => function ($query) use ($student) {
				$query->where('student_id', $student->id);
			}])
			->orderBy('created_at', 'desc')
			->get();
	}

	/**
	 * Get quiz details with questions and options
	 */
	public function getQuizDetails(Quiz $quiz, User|Student $student): array
	{
		$attempt = $quiz->attempts()
			->where('student_id', $student->id)
			->latest()
			->first();

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
			'show_answers' => $quiz->show_answers,
			'shuffle_questions' => $quiz->shuffle_questions,
			'shuffle_options' => $quiz->shuffle_options,
			'allow_review' => $quiz->allow_review,
			'status' => $quiz->status,
			'start_time' => $quiz->start_time,
			'end_time' => $quiz->end_time,
			'attempted' => $attempt ? true : false,
			'questions' => $quiz->questions->map(function ($question) {
				return [
					'id' => $question->id,
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
			}),
		];
	}

	/**
	 * Check if student can take quiz
	 */
	public function canStudentTakeQuiz(User|Student $student, Quiz $quiz): bool
	{
		// Check if quiz is published
		if ($quiz->status !== 'published') {
			return false;
		}

		// Check if quiz is within time window
		if ($quiz->start_time && $quiz->start_time->isFuture()) {
			return false;
		}

		if ($quiz->end_time && $quiz->end_time->isPast()) {
			return false;
		}

		// Check if student is in correct class
		if ($quiz->class_id) {
			$studentClassIds = $this->resolveStudentClassIds($student);
			
			if (!$studentClassIds->contains($quiz->class_id)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if student has attempted quiz
	 */
	public function hasStudentAttempted(User|Student $student, Quiz $quiz): bool
	{
		return $quiz->attempts()
			->where('student_id', $student->id)
			->where('status', '!=', 'in_progress')
			->exists();
	}

	/**
	 * Resolve the student's class ids from current class or enrollments.
	 */
	private function resolveStudentClassIds(User|Student $student): SupportCollection
	{
		$classIds = collect();

		if (!empty($student->school_class_id)) {
			$classIds->push($student->school_class_id);
		}

		if (method_exists($student, 'enrollments')) {
			$enrollments = $student->enrollments()
				->with('class_section.class_arm')
				->get()
				->pluck('class_section.class_arm.school_class_id');
			$classIds = $classIds->merge($enrollments);
		} elseif (method_exists($student, 'student_enrollments')) {
			$enrollments = $student->student_enrollments()
				->with('class_section.class_arm')
				->get()
				->pluck('class_section.class_arm.school_class_id');
			$classIds = $classIds->merge($enrollments);
		}

		return $classIds->filter()->unique()->values();
	}

	/**
	 * Create a new quiz
	 */
	public function createQuiz(array $data, User $creator): Quiz
	{
		$data['id'] = Str::uuid();
		$data['created_by'] = $creator->id;
		$data['school_id'] = $creator->school_id;
		$data['status'] = 'draft';

		return Quiz::create($data);
	}

	/**
	 * Update quiz
	 */
	public function updateQuiz(Quiz $quiz, array $data): bool
	{
		return $quiz->update($data);
	}

	/**
	 * Publish quiz
	 */
	public function publishQuiz(Quiz $quiz): bool
	{
		return $quiz->update(['status' => 'published']);
	}

	/**
	 * Unpublish quiz
	 */
	public function unpublishQuiz(Quiz $quiz): bool
	{
		return $quiz->update(['status' => 'draft']);
	}

	/**
	 * Close quiz
	 */
	public function closeQuiz(Quiz $quiz): bool
	{
		return $quiz->update(['status' => 'closed']);
	}

	/**
	 * Delete quiz
	 */
	public function deleteQuiz(Quiz $quiz): bool
	{
		return $quiz->delete();
	}

	/**
	 * Get quiz statistics
	 */
	public function getQuizStatistics(Quiz $quiz): array
	{
		$attempts = $quiz->attempts()->where('status', '!=', 'in_progress')->get();
		$results = $quiz->attempts()
			->whereHas('result')
			->with('result')
			->get();

		$totalAttempts = $attempts->count();
		$passedAttempts = $results->filter(fn($a) => $a->result && $a->result->status === 'pass')->count();

		return [
			'total_attempts' => $totalAttempts,
			'total_students' => $attempts->pluck('student_id')->unique()->count(),
			'passed' => $passedAttempts,
			'failed' => $totalAttempts - $passedAttempts,
			'average_score' => $results->count() > 0
				? $results->average('result.percentage')
				: 0,
			'pass_rate' => $totalAttempts > 0 ? ($passedAttempts / $totalAttempts) * 100 : 0,
		];
	}
}
