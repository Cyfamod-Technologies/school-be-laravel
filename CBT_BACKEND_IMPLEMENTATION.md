# CBT Backend Implementation Guide

## Database Migrations

Before the CBT system can function, you need to create the following migrations:

### 1. Create Quizzes Table

```bash
php artisan make:migration create_quizzes_table
```

**Migration content:**
```php
Schema::create('quizzes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('school_id');
    $table->string('title');
    $table->text('description')->nullable();
    $table->uuid('subject_id')->nullable();
    $table->uuid('class_id')->nullable();
    $table->uuid('created_by');
    $table->integer('duration_minutes');
    $table->integer('total_questions');
    $table->integer('passing_score');
    $table->boolean('show_answers')->default(true);
    $table->boolean('shuffle_questions')->default(false);
    $table->boolean('shuffle_options')->default(false);
    $table->boolean('allow_review')->default(true);
    $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
    $table->timestamp('start_time')->nullable();
    $table->timestamp('end_time')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
    $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
    $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('set null');
    $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
});
```

### 2. Create Quiz Questions Table

```bash
php artisan make:migration create_quiz_questions_table
```

**Migration content:**
```php
Schema::create('quiz_questions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('quiz_id');
    $table->text('question_text');
    $table->enum('question_type', ['mcq', 'multiple_select', 'true_false', 'short_answer']);
    $table->integer('marks');
    $table->integer('order');
    $table->string('image_url')->nullable();
    $table->text('explanation')->nullable();
    $table->timestamps();
    
    $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
    $table->index(['quiz_id', 'order']);
});
```

### 3. Create Quiz Options Table

```bash
php artisan make:migration create_quiz_options_table
```

**Migration content:**
```php
Schema::create('quiz_options', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('question_id');
    $table->text('option_text');
    $table->integer('order');
    $table->boolean('is_correct')->default(false);
    $table->string('image_url')->nullable();
    $table->timestamps();
    
    $table->foreign('question_id')->references('id')->on('quiz_questions')->onDelete('cascade');
    $table->index(['question_id', 'order']);
});
```

### 4. Create Quiz Attempts Table

```bash
php artisan make:migration create_quiz_attempts_table
```

**Migration content:**
```php
Schema::create('quiz_attempts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('quiz_id');
    $table->uuid('student_id');
    $table->uuid('session_id')->nullable();
    $table->uuid('term_id')->nullable();
    $table->timestamp('start_time');
    $table->timestamp('end_time')->nullable();
    $table->integer('duration_seconds')->nullable();
    $table->enum('status', ['in_progress', 'submitted', 'graded'])->default('in_progress');
    $table->string('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamps();
    
    $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
    $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
    $table->foreign('session_id')->references('id')->on('sessions')->onDelete('set null');
    $table->foreign('term_id')->references('id')->on('terms')->onDelete('set null');
    $table->index(['quiz_id', 'student_id']);
    $table->index(['student_id', 'status']);
});
```

### 5. Create Quiz Answers Table

```bash
php artisan make:migration create_quiz_answers_table
```

**Migration content:**
```php
Schema::create('quiz_answers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('attempt_id');
    $table->uuid('question_id');
    $table->uuid('selected_option_id')->nullable();
    $table->text('answer_text')->nullable();
    $table->boolean('is_correct')->default(false);
    $table->integer('marks_obtained')->default(0);
    $table->integer('time_spent_seconds')->nullable();
    $table->timestamp('answered_at');
    $table->timestamps();
    
    $table->foreign('attempt_id')->references('id')->on('quiz_attempts')->onDelete('cascade');
    $table->foreign('question_id')->references('id')->on('quiz_questions')->onDelete('cascade');
    $table->foreign('selected_option_id')->references('id')->on('quiz_options')->onDelete('set null');
    $table->index(['attempt_id', 'question_id']);
    $table->unique(['attempt_id', 'question_id']);
});
```

### 6. Create Quiz Results Table

```bash
php artisan make:migration create_quiz_results_table
```

**Migration content:**
```php
Schema::create('quiz_results', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('attempt_id')->unique();
    $table->uuid('quiz_id');
    $table->uuid('student_id');
    $table->integer('total_questions');
    $table->integer('attempted_questions');
    $table->integer('correct_answers');
    $table->integer('total_marks');
    $table->integer('marks_obtained');
    $table->decimal('percentage', 5, 2);
    $table->char('grade', 1);
    $table->enum('status', ['pass', 'fail']);
    $table->timestamp('submitted_at');
    $table->timestamp('graded_at')->nullable();
    $table->timestamps();
    
    $table->foreign('attempt_id')->references('id')->on('quiz_attempts')->onDelete('cascade');
    $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
    $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
    $table->index(['student_id', 'quiz_id']);
    $table->index(['status']);
});
```

## Run Migrations

```bash
php artisan migrate
```

## Add Permissions

Add these permissions to your system:

```php
// In your permission seeder or command
Permission::create(['name' => 'cbt.view', 'guard_name' => 'sanctum']);
Permission::create(['name' => 'cbt.manage', 'guard_name' => 'sanctum']);
```

## API Routes

Add these routes to `routes/api.php`:

```php
Route::middleware('auth:sanctum')->group(function () {
    // Quiz management
    Route::apiResource('quizzes', QuizController::class);
    Route::post('quizzes/{id}/publish', [QuizController::class, 'publish']);
    Route::post('quizzes/{id}/close', [QuizController::class, 'close']);
    Route::get('quizzes/{id}/statistics', [QuizController::class, 'statistics']);
    
    // Quiz attempts
    Route::apiResource('quiz-attempts', QuizAttemptController::class)->only(['store', 'show']);
    Route::get('quiz-attempts/{id}/timer', [QuizAttemptController::class, 'timer']);
    Route::post('quiz-attempts/{id}/submit', [QuizAttemptController::class, 'submit']);
    Route::post('quiz-attempts/{id}/end', [QuizAttemptController::class, 'end']);
    Route::get('quiz-attempts/history', [QuizAttemptController::class, 'history']);
    
    // Quiz answers
    Route::apiResource('quiz-answers', QuizAnswerController::class)->only(['store', 'update']);
    Route::get('quiz-attempts/{id}/answers', [QuizAnswerController::class, 'byAttempt']);
    
    // Quiz results
    Route::get('quiz-results/{id}', [QuizResultController::class, 'show']);
    Route::get('quiz-results/by-quiz/{quizId}', [QuizResultController::class, 'byQuiz']);
    Route::get('quizzes/{quizId}/statistics', [QuizResultController::class, 'statistics']);
    Route::get('student/cbt-report', [QuizResultController::class, 'studentReport']);
});
```

## Services

### QuizService
- `getStudentQuizzes(User $student)` - Get all available quizzes for a student
- `getQuizDetails(Quiz $quiz, User $student)` - Get quiz with questions and options
- `canStudentTakeQuiz(User $student, Quiz $quiz)` - Check if student can access quiz
- `hasStudentAttempted(User $student, Quiz $quiz)` - Check if student attempted quiz
- `createQuiz(array $data, User $creator)` - Create new quiz
- `updateQuiz(Quiz $quiz, array $data)` - Update quiz
- `publishQuiz(Quiz $quiz)` - Publish quiz
- `closeQuiz(Quiz $quiz)` - Close quiz
- `deleteQuiz(Quiz $quiz)` - Delete quiz
- `getQuizStatistics(Quiz $quiz)` - Get quiz statistics

### QuestionService
- `addQuestion(Quiz $quiz, array $data)` - Add question to quiz
- `updateQuestion(QuizQuestion $question, array $data)` - Update question
- `deleteQuestion(QuizQuestion $question)` - Delete question
- `addOption(QuizQuestion $question, array $data)` - Add option to question
- `updateOption(QuizOption $option, array $data)` - Update option
- `deleteOption(QuizOption $option)` - Delete option
- `reorderQuestions(Quiz $quiz, array $order)` - Reorder questions
- `getShuffledQuestions(Quiz $quiz)` - Get shuffled questions if enabled
- `getQuestionWithOptions(QuizQuestion $question)` - Get question with all options

### AttemptService
- `startAttempt(Quiz $quiz, User $student)` - Start new quiz attempt
- `getAttempt(QuizAttempt $attempt)` - Get attempt details
- `getRemainingTime(QuizAttempt $attempt)` - Get remaining time in seconds
- `hasTimeExpired(QuizAttempt $attempt)` - Check if time expired
- `submitAttempt(QuizAttempt $attempt)` - Submit attempt
- `endAttemptEarly(QuizAttempt $attempt)` - End attempt early
- `getStudentAttemptHistory(User $student)` - Get student's attempt history

### AnswerService
- `submitAnswer(QuizAttempt $attempt, QuizQuestion $question, array $data)` - Submit/update answer
- `getAttemptAnswers(QuizAttempt $attempt)` - Get all answers for attempt
- `updateAnswer(QuizAnswer $answer, array $data)` - Update answer for review

### ScoringService
- `calculateAttemptScore(QuizAttempt $attempt)` - Calculate score and create result
- `evaluateAnswer(QuizQuestion $question, QuizAnswer $answer)` - Evaluate answer correctness
- `determineGrade(float $percentage)` - Determine grade from percentage
- `getQuizStatistics(int $quizId)` - Get quiz statistics

## Controllers

### QuizController
- `index()` - List student's available quizzes
- `show(id)` - Get quiz details with questions
- `store()` - Create new quiz
- `update(id)` - Update quiz
- `destroy(id)` - Delete quiz
- `publish(id)` - Publish quiz
- `close(id)` - Close quiz
- `statistics(id)` - Get quiz statistics

### QuizAttemptController
- `store()` - Start quiz attempt
- `show(id)` - Get attempt details
- `timer(id)` - Get remaining time
- `submit(id)` - Submit attempt
- `end(id)` - End attempt early
- `history()` - Get student's attempt history

### QuizAnswerController
- `store()` - Submit answer
- `byAttempt(id)` - Get all answers for attempt
- `update(id)` - Update answer (review mode)

### QuizResultController
- `show(id)` - Get result details
- `byQuiz(quizId)` - Get all results for quiz
- `statistics(quizId)` - Get quiz statistics
- `studentReport()` - Get student's CBT report

## Configuration

Create a `config/cbt.php` file for CBT settings:

```php
return [
    'max_attempts' => env('CBT_MAX_ATTEMPTS', 1),
    'timer_warning_minutes' => env('CBT_TIMER_WARNING_MINUTES', 5),
    'auto_submit_on_timeout' => env('CBT_AUTO_SUBMIT_ON_TIMEOUT', true),
    'enable_proctoring' => env('CBT_ENABLE_PROCTORING', false),
    'shuffle_questions' => env('CBT_SHUFFLE_QUESTIONS', true),
    'shuffle_options' => env('CBT_SHUFFLE_OPTIONS', true),
];
```

## Next Steps

1. ✅ Create and run migrations
2. ✅ Add permissions to database
3. ✅ Add routes to `routes/api.php`
4. Test all endpoints
5. Add frontend components integration
6. Set up webhook for auto-submit on timeout (optional)
7. Add analytics and reporting

---

**Created**: January 10, 2026
**Status**: Ready for Migration and Testing
