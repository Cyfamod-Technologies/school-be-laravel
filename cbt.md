# Computer-Based Test (CBT) System Architecture

## Overview

This document outlines the complete structure and architecture for implementing a Computer-Based Test (CBT) system in the School Management System, covering both backend (Laravel/PHP) and frontend (Next.js/React) implementations.

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Backend Structure](#backend-structure)
3. [Frontend Structure](#frontend-structure)
4. [Database Schema](#database-schema)
5. [API Endpoints](#api-endpoints)
6. [Frontend Components](#frontend-components)
7. [Question Types](#question-types)
8. [Scoring Logic](#scoring-logic)
9. [Security Considerations](#security-considerations)
10. [User Workflows](#user-workflows)

## System Architecture

### High-Level Flow

```
┌─────────────┐
│   Student   │
└──────┬──────┘
       │
       ▼
┌─────────────────────────┐
│   Frontend (Next.js)    │
│  - Question Display     │
│  - Timer Management     │
│  - Answer Collection    │
│  - Real-time Sync       │
└──────┬──────────────────┘
       │ HTTP/REST API
       ▼
┌─────────────────────────┐
│   Backend (Laravel)     │
│  - Quiz Logic           │
│  - Answer Processing    │
│  - Scoring Calculation  │
│  - Data Persistence     │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│   Database (MySQL)      │
│  - Questions            │
│  - Attempts             │
│  - Answers              │
│  - Results              │
└─────────────────────────┘
```

## Backend Structure

### Directory Structure

```
app/
├── Models/
│   ├── Quiz.php
│   ├── QuizQuestion.php
│   ├── QuizOption.php
│   ├── QuizAttempt.php
│   ├── QuizAnswer.php
│   └── QuizResult.php
│
├── Services/
│   ├── Quiz/
│   │   ├── QuizService.php          # Main quiz logic
│   │   ├── QuestionService.php      # Question management
│   │   ├── ScoringService.php       # Scoring calculations
│   │   ├── TimerService.php         # Time tracking
│   │   └── ValidationService.php    # Answer validation
│   │
├── Http/Controllers/Api/V1/
│   ├── QuizController.php           # Quiz CRUD
│   ├── QuizQuestionController.php   # Question management
│   ├── QuizAttemptController.php    # Attempt tracking
│   ├── QuizAnswerController.php     # Answer submission
│   └── QuizResultController.php     # Results & reporting
│
├── Events/
│   ├── QuizAttemptStarted.php
│   ├── QuestionAnswered.php
│   ├── QuizCompleted.php
│   └── TimerExpired.php
│
└── Jobs/
    ├── CalculateQuizResults.php
    └── SendQuizNotifications.php
```

### Core Models

#### Quiz Model
```php
Quiz
├── id (UUID)
├── title (string)
├── description (text)
├── subject_id (UUID) → foreign key to subjects
├── class_id (UUID) → foreign key to classes
├── created_by (UUID) → foreign key to users
├── duration_minutes (integer)
├── total_questions (integer)
├── passing_score (integer/percentage)
├── show_answers (boolean) → reveal after submission
├── shuffle_questions (boolean)
├── shuffle_options (boolean)
├── allow_review (boolean) → review before submit
├── status (enum: draft, published, closed)
├── start_time (timestamp)
├── end_time (timestamp)
├── created_at
├── updated_at
└── deleted_at
```

#### QuizQuestion Model
```php
QuizQuestion
├── id (UUID)
├── quiz_id (UUID) → foreign key
├── question_text (text)
├── question_type (enum: mcq, multiple_select, true_false, short_answer)
├── marks (integer)
├── order (integer)
├── image_url (string) → optional for visual questions
├── explanation (text) → shown after answer
├── created_at
└── updated_at
```

#### QuizOption Model
```php
QuizOption
├── id (UUID)
├── question_id (UUID) → foreign key
├── option_text (text)
├── order (integer)
├── is_correct (boolean)
├── image_url (string) → optional
└── created_at
```

#### QuizAttempt Model
```php
QuizAttempt
├── id (UUID)
├── quiz_id (UUID) → foreign key
├── student_id (UUID) → foreign key to users
├── session_id (UUID) → foreign key to sessions
├── term_id (UUID) → foreign key to terms
├── start_time (timestamp)
├── end_time (timestamp)
├── duration_seconds (integer)
├── status (enum: in_progress, submitted, graded)
├── ip_address (string) → for proctoring
├── user_agent (string) → browser info
├── created_at
└── updated_at
```

#### QuizAnswer Model
```php
QuizAnswer
├── id (UUID)
├── attempt_id (UUID) → foreign key
├── question_id (UUID) → foreign key
├── selected_option_id (UUID) → foreign key (nullable for short answers)
├── answer_text (text) → for short answer questions
├── is_correct (boolean) → determined after submission
├── marks_obtained (integer)
├── answered_at (timestamp)
├── time_spent_seconds (integer)
└── created_at
```

#### QuizResult Model
```php
QuizResult
├── id (UUID)
├── attempt_id (UUID) → foreign key
├── quiz_id (UUID) → foreign key
├── student_id (UUID) → foreign key
├── total_questions (integer)
├── attempted_questions (integer)
├── correct_answers (integer)
├── total_marks (integer)
├── marks_obtained (integer)
├── percentage (decimal)
├── grade (string) → A, B, C, D, F based on grading scale
├── status (enum: pass, fail)
├── submitted_at (timestamp)
├── graded_at (timestamp)
└── created_at
```

### Service Layer

#### QuizService.php
```php
class QuizService {
    public function createQuiz(array $data): Quiz
    public function publishQuiz(Quiz $quiz): bool
    public function closeQuiz(Quiz $quiz): bool
    public function getStudentQuizzes(User $student): Collection
    public function getQuizDetails(Quiz $quiz, User $student): array
    public function canStudentTakeQuiz(User $student, Quiz $quiz): bool
    public function hasStudentAttempted(User $student, Quiz $quiz): bool
}
```

#### QuestionService.php
```php
class QuestionService {
    public function addQuestion(Quiz $quiz, array $data): QuizQuestion
    public function updateQuestion(QuizQuestion $question, array $data): bool
    public function deleteQuestion(QuizQuestion $question): bool
    public function reorderQuestions(Quiz $quiz, array $order): bool
    public function getShuffledQuestions(Quiz $quiz): Collection
    public function getShuffledOptions(QuizQuestion $question): Collection
}
```

#### ScoringService.php
```php
class ScoringService {
    public function calculateAttemptScore(QuizAttempt $attempt): array
    public function evaluateAnswer(QuizQuestion $question, QuizAnswer $answer): int
    public function determinGrade(int $percentage): string
    public function generateReport(QuizAttempt $attempt): array
    public function calculateStatistics(Quiz $quiz): array
}
```

#### TimerService.php
```php
class TimerService {
    public function startTimer(QuizAttempt $attempt): void
    public function getRemainingTime(QuizAttempt $attempt): int
    public function hasTimeExpired(QuizAttempt $attempt): bool
    public function extendTime(QuizAttempt $attempt, int $minutes): void
}
```

#### ValidationService.php
```php
class ValidationService {
    public function validateQuizAccess(User $student, Quiz $quiz): bool
    public function validateAnswerSubmission(QuizAttempt $attempt, array $answer): bool
    public function validateQuestionAnswer(QuizQuestion $question, array $answer): bool
    public function preventCheating(QuizAttempt $attempt): bool
}
```

## Frontend Structure

### Directory Structure

```
nextjs/
├── app/
│   └── (app)/
│       ├── v26/
│       │   └── cbt/
│       │       ├── layout.tsx
│       │       ├── page.tsx                    # CBT home
│       │       ├── [quizId]/
│       │       │   └── take/
│       │       │       └── page.tsx            # Quiz taking interface
│       │       ├── results/
│       │       │   └── [attemptId]/
│       │       │       └── page.tsx            # Result display
│       │       └── history/
│       │           └── page.tsx                # Attempt history
│
├── components/
│   └── cbt/
│       ├── QuizCard.tsx                       # Quiz listing card
│       ├── QuizInterface.tsx                  # Main quiz container
│       ├── QuestionDisplay.tsx                # Question rendering
│       ├── OptionButton.tsx                   # Option selector
│       ├── QuestionNavigation.tsx             # Q navigation sidebar
│       ├── Timer.tsx                          # Countdown timer
│       ├── ProgressBar.tsx                    # Progress indicator
│       ├── QuestionPalette.tsx                # Visual question tracker
│       ├── SubmissionConfirm.tsx              # Confirmation dialog
│       ├── ResultsSummary.tsx                 # Results overview
│       ├── DetailedResults.tsx                # Question-by-question review
│       └── QuestionTypes/
│           ├── MCQQuestion.tsx                # Single choice
│           ├── MultiSelectQuestion.tsx        # Multiple choice
│           ├── TrueFalseQuestion.tsx          # T/F questions
│           └── ShortAnswerQuestion.tsx        # Text input
│
├── hooks/
│   ├── useCBTQuiz.ts                          # Quiz state management
│   ├── useTimer.ts                            # Timer logic
│   ├── useAnswerTracking.ts                   # Answer history
│   └── useQuestionNavigation.ts               # Q navigation
│
├── lib/
│   └── cbt/
│       ├── quizApi.ts                         # API calls
│       ├── timerUtils.ts                      # Timer utilities
│       ├── answerValidator.ts                 # Answer validation
│       ├── scoringCalculator.ts               # Score calculation
│       └── cbtConfig.ts                       # Configuration
│
└── types/
    └── cbt.ts                                 # TypeScript types
```

### Key Types

```typescript
// cbt.ts
export interface Quiz {
  id: string;
  title: string;
  description: string;
  subject_id: string;
  class_id: string;
  duration_minutes: number;
  total_questions: number;
  passing_score: number;
  show_answers: boolean;
  shuffle_questions: boolean;
  shuffle_options: boolean;
  allow_review: boolean;
  status: 'draft' | 'published' | 'closed';
  start_time: string;
  end_time: string;
  created_at: string;
  updated_at: string;
}

export interface QuizQuestion {
  id: string;
  quiz_id: string;
  question_text: string;
  question_type: 'mcq' | 'multiple_select' | 'true_false' | 'short_answer';
  marks: number;
  order: number;
  image_url?: string;
  explanation?: string;
  options: QuizOption[];
}

export interface QuizOption {
  id: string;
  question_id: string;
  option_text: string;
  order: number;
  image_url?: string;
}

export interface QuizAttempt {
  id: string;
  quiz_id: string;
  student_id: string;
  start_time: string;
  end_time?: string;
  status: 'in_progress' | 'submitted' | 'graded';
  answers: QuizAnswer[];
}

export interface QuizAnswer {
  id: string;
  attempt_id: string;
  question_id: string;
  selected_option_id?: string;
  answer_text?: string;
  is_correct: boolean;
  marks_obtained: number;
  answered_at: string;
}

export interface QuizResult {
  id: string;
  attempt_id: string;
  quiz_id: string;
  total_questions: number;
  attempted_questions: number;
  correct_answers: number;
  total_marks: number;
  marks_obtained: number;
  percentage: number;
  grade: string;
  status: 'pass' | 'fail';
  submitted_at: string;
  graded_at: string;
}
```

### State Management (useCBTQuiz Hook)

```typescript
interface CBTQuizState {
  quiz: Quiz | null;
  attempt: QuizAttempt | null;
  currentQuestionIndex: number;
  answers: Map<string, QuizAnswer>;
  timeRemaining: number;
  isSubmitting: boolean;
  showConfirmation: boolean;
}

interface CBTQuizActions {
  loadQuiz(quizId: string): Promise<void>;
  startAttempt(): Promise<void>;
  selectOption(questionId: string, optionId: string): void;
  submitAnswer(questionId: string, answer: QuizAnswer): Promise<void>;
  navigateToQuestion(index: number): void;
  submitQuiz(): Promise<QuizResult>;
  endQuizEarly(): Promise<void>;
}
```

## Database Schema

### Complete ER Diagram

```
┌─────────────┐
│   quizzes   │
├─────────────┤
│ id (PK)     │
│ title       │
│ duration    │
│ marks       │
├─────────────┤
      │
      │ 1:M
      ▼
┌──────────────────┐      ┌─────────────────┐
│ quiz_questions   │◄─────┤ quiz_options    │
├──────────────────┤      ├─────────────────┤
│ id (PK)          │ 1:M  │ id (PK)         │
│ quiz_id (FK)     │      │ question_id(FK) │
│ question_text    │      │ option_text     │
│ question_type    │      │ is_correct      │
│ marks            │      └─────────────────┘
└──────────────────┘
      │
      │ 1:M
      ▼
┌──────────────────┐      ┌─────────────────┐
│ quiz_attempts    │◄─────┤ quiz_answers    │
├──────────────────┤      ├─────────────────┤
│ id (PK)          │ 1:M  │ id (PK)         │
│ quiz_id (FK)     │      │ attempt_id (FK) │
│ student_id (FK)  │      │ question_id(FK) │
│ start_time       │      │ option_id (FK)  │
│ end_time         │      │ is_correct      │
└──────────────────┘      └─────────────────┘
      │
      │ 1:1
      ▼
┌──────────────────┐
│ quiz_results     │
├──────────────────┤
│ id (PK)          │
│ attempt_id (FK)  │
│ marks_obtained   │
│ percentage       │
│ grade            │
│ status (pass/fail)
└──────────────────┘
```

## API Endpoints

### Quiz Management Endpoints

```
GET    /api/v1/quizzes                    # List quizzes
POST   /api/v1/quizzes                    # Create quiz (admin)
GET    /api/v1/quizzes/{id}               # Get quiz details
PUT    /api/v1/quizzes/{id}               # Update quiz (admin)
DELETE /api/v1/quizzes/{id}               # Delete quiz (admin)
POST   /api/v1/quizzes/{id}/publish       # Publish quiz
POST   /api/v1/quizzes/{id}/close         # Close quiz

POST   /api/v1/quizzes/{id}/questions     # Add question
PUT    /api/v1/quizzes/{id}/questions/{qId}  # Update question
DELETE /api/v1/quizzes/{id}/questions/{qId}  # Delete question
POST   /api/v1/quizzes/{id}/questions/reorder # Reorder questions
```

### Attempt Endpoints

```
POST   /api/v1/quiz-attempts              # Start quiz attempt
GET    /api/v1/quiz-attempts/{id}         # Get attempt details
GET    /api/v1/quiz-attempts/{id}/timer   # Get remaining time
POST   /api/v1/quiz-attempts/{id}/submit  # Submit quiz
POST   /api/v1/quiz-attempts/{id}/end     # End quiz early
```

### Answer Endpoints

```
POST   /api/v1/quiz-answers               # Submit answer
PUT    /api/v1/quiz-answers/{id}          # Update answer (review mode)
GET    /api/v1/quiz-attempts/{id}/answers # Get all answers
```

### Results Endpoints

```
GET    /api/v1/quiz-results/{attemptId}   # Get result details
GET    /api/v1/quiz-results/by-quiz/{id}  # Get quiz results (admin)
GET    /api/v1/quiz-attempts/{id}/history # Get attempt history
```

### Analytics Endpoints

```
GET    /api/v1/quizzes/{id}/statistics    # Quiz statistics
GET    /api/v1/quizzes/{id}/class-report  # Class performance
GET    /api/v1/student/cbt-report          # Student CBT report
```

## Frontend Components

### QuizInterface.tsx (Main Container)

```typescript
export interface QuizInterfaceProps {
  quizId: string;
  readOnly?: boolean;
  showResults?: boolean;
}

export const QuizInterface: React.FC<QuizInterfaceProps> = ({
  quizId,
  readOnly = false,
  showResults = false,
}) => {
  // Quiz state
  // Timer management
  // Answer tracking
  // Question navigation
  // Submission handling
}
```

### QuestionDisplay.tsx

```typescript
interface QuestionDisplayProps {
  question: QuizQuestion;
  currentAnswer?: QuizAnswer;
  readOnly?: boolean;
  showCorrectAnswer?: boolean;
  onAnswerChange: (answer: QuizAnswer) => void;
}

export const QuestionDisplay: React.FC<QuestionDisplayProps> = ({
  question,
  currentAnswer,
  readOnly,
  showCorrectAnswer,
  onAnswerChange,
}) => {
  // Render based on question type
  // Handle option selection
  // Show explanation if available
}
```

### Timer.tsx

```typescript
interface TimerProps {
  initialSeconds: number;
  onTimeExpired: () => void;
  onWarning?: (secondsRemaining: number) => void;
}

export const Timer: React.FC<TimerProps> = ({
  initialSeconds,
  onTimeExpired,
  onWarning,
}) => {
  // Countdown logic
  // Warning at 5 minutes, 1 minute
  // Trigger submission on timeout
}
```

### QuestionPalette.tsx

```typescript
interface QuestionPaletteProps {
  totalQuestions: number;
  answeredQuestions: Map<string, boolean>;
  reviewedQuestions?: Set<string>;
  currentQuestion: number;
  onSelectQuestion: (index: number) => void;
  allowReview: boolean;
}

export const QuestionPalette: React.FC<QuestionPaletteProps> = ({
  totalQuestions,
  answeredQuestions,
  reviewedQuestions,
  currentQuestion,
  onSelectQuestion,
  allowReview,
}) => {
  // Visual representation of all questions
  // Color coding: answered, unanswered, reviewed, current
  // Click to navigate
}
```

## Question Types

### 1. Multiple Choice (MCQ)
- Single correct answer
- Student selects one option
- Radio button interface
- Mark allocation on correct answer

### 2. Multiple Select
- Multiple correct answers possible
- Student selects multiple options
- Checkbox interface
- Mark allocation on percentage of correct selections

### 3. True/False
- Binary choice question
- Toggle or two buttons
- Mark allocation on correct answer

### 4. Short Answer
- Text input question
- Can be manually graded or keyword-matched
- Word limit optional
- Placeholder text supported

## Scoring Logic

### Automatic Scoring (MCQ, True/False)

```
Score = (Correct Answers / Total Questions) * Total Marks

Example:
- 10 questions, 10 marks each
- Student answers 8 correctly
- Score = (8/10) * 100 = 80 marks
```

### Partial Marking (Multiple Select)

```
Marks per Question = Question Marks / Total Correct Options

Score Calculation:
- Question has 3 correct options, worth 3 marks
- Student selects 2 out of 3 correct options
- Marks obtained = 2 marks
- Optional: Deduct for wrong selections
```

### Manual Grading (Short Answer)

```
- Teacher reviews short answer responses
- Assigns marks based on rubric
- Can provide feedback
- Updates student result
```

### Grade Calculation

```php
$percentage = ($marksObtained / $totalMarks) * 100;

if ($percentage >= 90) {
    $grade = 'A'; // Excellent
} elseif ($percentage >= 80) {
    $grade = 'B'; // Very Good
} elseif ($percentage >= 70) {
    $grade = 'C'; // Good
} elseif ($percentage >= 60) {
    $grade = 'D'; // Pass
} else {
    $grade = 'F'; // Fail
}

$status = $percentage >= $passingScore ? 'pass' : 'fail';
```

## Security Considerations

### Backend Security

1. **Authentication & Authorization**
   - Verify student is authenticated
   - Check if student has access to quiz
   - Verify student belongs to correct class

2. **Attempt Validation**
   - Only one active attempt per student per quiz
   - Validate attempt status before accepting answers
   - Prevent double submissions

3. **Answer Integrity**
   - Validate answers against quiz questions
   - Prevent option injection/manipulation
   - Log all answer submissions with timestamps

4. **Rate Limiting**
   - Limit API calls per student
   - Prevent answer bombing/brute force

5. **Data Validation**
   - Sanitize input
   - Validate question/option IDs
   - Check for SQL injection

### Frontend Security

1. **Local Storage**
   - Encrypt sensitive data
   - Clear on logout
   - Store only necessary data (not answers)

2. **Tab/Window Switching**
   - Detect when user leaves quiz tab
   - Show warning on return
   - Log time away

3. **Right-click & Copy Prevention**
   - Disable right-click context menu
   - Disable text selection on questions
   - Disable keyboard shortcuts (Ctrl+C, Ctrl+V)

4. **Proctoring Features**
   - Require webcam during test (optional)
   - Detect full-screen exit
   - Track mouse movements
   - Log IP address and user agent

### Network Security

1. **HTTPS Only**
   - Enforce SSL/TLS
   - Secure cookies (HttpOnly, Secure, SameSite)

2. **CORS Configuration**
   - Restrict to authorized domains
   - Validate origin headers

3. **API Rate Limiting**
   - Throttle requests per IP
   - Prevent DDoS attacks

## User Workflows

### Student Workflow

```
1. Login to System
   ↓
2. Browse Available Quizzes
   ├─ Filter by Subject/Class
   ├─ View Quiz Details (title, duration, marks)
   ├─ Check eligibility
   └─ See attempt history
   ↓
3. Start Quiz
   ├─ Read instructions
   ├─ Start Timer
   └─ Load Questions
   ↓
4. Answer Questions
   ├─ Read question
   ├─ View options/input answer
   ├─ Navigate between questions
   ├─ Use Review feature (if allowed)
   └─ Track progress
   ↓
5. Submit Quiz
   ├─ Review answers (optional)
   ├─ Confirm submission
   └─ Upload responses
   ↓
6. View Results
   ├─ See score/percentage
   ├─ View detailed breakdown
   ├─ Read explanations (if allowed)
   └─ Analyze performance
   ↓
7. Download Report (if allowed)
```

### Teacher/Admin Workflow

```
1. Login to System
   ↓
2. Create Quiz
   ├─ Set basic details (title, duration, marks)
   ├─ Add questions
   │  ├─ Select question type
   │  ├─ Add options for MCQ
   │  ├─ Mark correct options
   │  └─ Add explanations
   ├─ Review & Edit
   └─ Save as Draft
   ↓
3. Publish Quiz
   ├─ Set start/end dates
   ├─ Configure options (shuffle, review)
   ├─ Select classes/students
   └─ Publish to students
   ↓
4. Monitor Attempts
   ├─ See real-time attempt progress
   ├─ Extend time if needed
   ├─ Force end attempt (if malpractice)
   └─ View student performance
   ↓
5. Grade Responses
   ├─ Review short answer responses
   ├─ Assign marks
   ├─ Provide feedback
   └─ Publish results
   ↓
6. Analyze Results
   ├─ View class statistics
   ├─ Identify weak areas
   ├─ Generate reports
   └─ Export data
```

### Admin Workflow

```
1. Quiz Management
   ├─ Create/Edit/Delete quizzes
   ├─ Manage question bank
   ├─ Set grading scales
   └─ Configure CBT settings
   ↓
2. User Management
   ├─ Assign quizzes to classes
   ├─ Manage student access
   ├─ Monitor proctoring
   └─ Handle violations
   ↓
3. Reporting
   ├─ Generate school-wide reports
   ├─ Track quiz statistics
   ├─ Analyze trends
   └─ Export data for analysis
```

## Implementation Timeline

### Phase 1: Core (Week 1-2)
- [x] Database schema
- [x] Quiz & Question models
- [x] Basic API endpoints
- [x] Question types (MCQ, T/F)

### Phase 2: Attempts & Answers (Week 3-4)
- [ ] Attempt management
- [ ] Answer submission
- [ ] Timer functionality
- [ ] Basic scoring

### Phase 3: Results & UI (Week 5-6)
- [ ] Results calculation
- [ ] Result display components
- [ ] Quiz interface
- [ ] Question navigation

### Phase 4: Advanced Features (Week 7-8)
- [ ] Review functionality
- [ ] Analytics & reporting
- [ ] Proctoring features
- [ ] Bulk import questions

### Phase 5: Polish & Security (Week 9)
- [ ] Security audit
- [ ] Performance optimization
- [ ] Testing
- [ ] Documentation

## Configuration

### Environment Variables

```env
# Backend
CBT_MAX_ATTEMPTS=1
CBT_TIMER_WARNING_MINUTES=5
CBT_AUTO_SUBMIT_ON_TIMEOUT=true
CBT_ENABLE_PROCTORING=false
CBT_SHUFFLE_QUESTIONS=true
CBT_SHUFFLE_OPTIONS=true

# Frontend
NEXT_PUBLIC_CBT_MAX_UPLOAD_SIZE=10485760
NEXT_PUBLIC_CBT_ENABLE_REVIEW=true
NEXT_PUBLIC_CBT_SHOW_RESULTS_IMMEDIATELY=false
```

## Testing Strategy

### Backend Tests
- Quiz creation and publishing
- Attempt lifecycle
- Answer validation
- Scoring calculations
- Security validations

### Frontend Tests
- Component rendering
- Timer functionality
- Answer selection and tracking
- Navigation between questions
- Submission process

### Integration Tests
- End-to-end quiz taking
- Result generation
- Data persistence
- API communication

## Future Enhancements

1. **AI-Powered Features**
   - Adaptive difficulty based on performance
   - Question recommendations

2. **Advanced Proctoring**
   - Facial recognition
   - AI-based suspicious activity detection

3. **Mobile Support**
   - Responsive design
   - Offline answer caching

4. **Question Banking**
   - Question template library
   - Automatic test generation

5. **Analytics**
   - Learning analytics dashboard
   - Item analysis & statistics
   - Comparative reports

6. **Integration**
   - LMS integration
   - Third-party proctoring services
   - Certificate generation

## References

- [Laravel Documentation](https://laravel.com/docs)
- [Next.js Documentation](https://nextjs.org/docs)
- [OpenAPI/Swagger Specification](https://swagger.io/)
- [MySQL Documentation](https://dev.mysql.com/doc/)

---

**Last Updated**: January 10, 2026
**Version**: 1.0
**Status**: Draft
