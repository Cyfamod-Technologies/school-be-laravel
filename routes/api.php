<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SchoolRegistrationController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\AcademicSessionController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\SubjectAssignmentController;
use App\Http\Controllers\Api\V1\SubjectTeacherAssignmentController;
use App\Http\Controllers\Api\V1\ClassTeacherAssignmentController;
use App\Http\Controllers\Api\V1\GradeScaleController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\AssessmentComponentController;
use App\Http\Controllers\Api\V1\ResultController;
use App\Http\Controllers\Api\V1\StudentSkillRatingController;
use App\Http\Controllers\Api\V1\ResultPinController;
use App\Http\Controllers\Api\V1\StudentTermSummaryController;
use App\Http\Controllers\Api\V1\SkillCategoryController;
use App\Http\Controllers\Api\V1\SkillTypeController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\StudentBulkUploadController;
use App\Http\Controllers\Api\V1\AcademicAnalyticsController;
use App\Http\Controllers\Api\V1\StaffAttendanceController;
use App\Http\Controllers\Api\V1\StudentAttendanceController;
use App\Http\Controllers\Api\V1\FeeItemController;
use App\Http\Controllers\Api\V1\FeeStructureController;
use App\Http\Controllers\Api\V1\BankDetailController;
use App\Http\Controllers\Api\V1\PermissionHierarchyController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserRoleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\StaffSelfController;
use App\Http\Controllers\Api\V1\TeacherDashboardController;
use App\Http\Controllers\Api\V1\StudentAuthController;
use App\Http\Controllers\ResultViewController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\QuizController;
use App\Http\Controllers\Api\V1\QuizQuestionController;
use App\Http\Controllers\Api\V1\QuizAttemptController;
use App\Http\Controllers\Api\V1\QuizAnswerController;
use App\Http\Controllers\Api\V1\QuizResultController;
use App\Http\Controllers\Api\V1\AssessmentComponentStructureController;
use App\Http\Controllers\Api\V1\CbtAssessmentLinkController;

$host = parse_url(config('app.url'), PHP_URL_HOST);

Route::domain('{subdomain}.' . $host)->group(function () {
    // Add your school-specific routes here
});


Route::get('/migrate', [\App\Http\Controllers\MigrateController::class, 'migrate']);

Route::prefix('api/v1')->group(function () {
    Route::post('/register-school', [SchoolController::class, 'register']);
    Route::post('/login', [SchoolController::class, 'login']);
    Route::get('/email/verify', [EmailVerificationController::class, 'verify'])
        ->name('api.v1.email.verify');

    Route::post('/password/forgot', [PasswordResetController::class, 'request']);
    Route::post('/password/reset', [PasswordResetController::class, 'reset']);

        Route::prefix('student')->group(function () {
            Route::post('login', [StudentAuthController::class, 'login']);

            Route::middleware('auth:student')->group(function () {
                Route::post('logout', [StudentAuthController::class, 'logout']);
                Route::get('profile', [StudentAuthController::class, 'profile']);
                Route::get('sessions', [StudentAuthController::class, 'sessions']);
                Route::post('results/preview', [StudentAuthController::class, 'previewResult']);
                Route::get('results/download', [StudentAuthController::class, 'downloadResult']);
            });
        });

        Route::get('cbt/public-quizzes', [QuizController::class, 'publicIndex']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [SchoolController::class, 'logout']);
        Route::get('/school', [SchoolController::class, 'showSchoolProfile']);
        Route::put('/school', [SchoolController::class, 'updateSchoolProfile']);
        Route::get('/user', [SchoolController::class, 'showSchoolAdminProfile']);            
        Route::put('/user', [SchoolController::class, 'updateSchoolAdminProfile']);

        // RBAC - Permissions
        Route::get('permissions', [PermissionController::class, 'index'])
            ->name('permissions.index');
        Route::post('permissions', [PermissionController::class, 'store'])
            ->name('permissions.store');
        Route::get('permissions/{permission}', [PermissionController::class, 'show'])
            ->whereNumber('permission')
            ->name('permissions.show');
        Route::put('permissions/{permission}', [PermissionController::class, 'update'])
            ->whereNumber('permission')
            ->name('permissions.update');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])
            ->whereNumber('permission')
            ->name('permissions.destroy');

        Route::get('permissions/hierarchy', [PermissionHierarchyController::class, 'index'])
            ->name('permissions.hierarchy.index');

        // RBAC - Roles
        Route::get('roles', [RoleController::class, 'index'])
            ->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])
            ->name('roles.store');
        Route::get('roles/{role}', [RoleController::class, 'show'])
            ->whereNumber('role')
            ->name('roles.show');
        Route::put('roles/{role}', [RoleController::class, 'update'])
            ->whereNumber('role')
            ->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])
            ->whereNumber('role')
            ->name('roles.destroy');

        // RBAC - User Roles
        Route::get('users', [UserController::class, 'index'])
            ->name('users.index');
        Route::get('users/{user}/roles', [UserRoleController::class, 'index'])
            ->whereUuid('user')
            ->name('users.roles.index');
        Route::put('users/{user}/roles', [UserRoleController::class, 'update'])
            ->whereUuid('user')
            ->name('users.roles.update');

        // Academic Session Routes
        Route::apiResource('sessions', AcademicSessionController::class);
        Route::get('sessions/{session}/terms', [AcademicSessionController::class, 'getTermsForSession']);
        Route::post('sessions/{session}/terms', [AcademicSessionController::class, 'storeTerm']);
        Route::get('terms', [AcademicSessionController::class, 'getAllTerms']);
        Route::get('terms/{term}', [AcademicSessionController::class, 'showTerm']);
        Route::put('terms/{term}', [AcademicSessionController::class, 'updateTerm']);
        Route::delete('terms/{term}', [AcademicSessionController::class, 'destroyTerm']);

        // Class, Class Arm, and Class Arm Section Routes
        Route::apiResource('classes', ClassController::class)->parameters([
            'classes' => 'schoolClass'
        ]);
        Route::prefix('classes/{schoolClass}')
            ->whereUuid('schoolClass')
            ->group(function () {
                Route::get('arms', [ClassController::class, 'indexArms']);
                Route::post('arms', [ClassController::class, 'storeArm']);
                Route::get('arms/{armId}', [ClassController::class, 'showArm'])->whereUuid('armId');
                Route::put('arms/{armId}', [ClassController::class, 'updateArm'])->whereUuid('armId');
                Route::delete('arms/{armId}', [ClassController::class, 'destroyArm'])->whereUuid('armId');

                Route::prefix('arms/{armId}')
                    ->whereUuid('armId')
                    ->group(function () {
                        Route::get('sections', [ClassController::class, 'indexSections']);
                        Route::post('sections', [ClassController::class, 'storeSection']);
                        Route::get('sections/{sectionId}', [ClassController::class, 'showSection'])->whereUuid('sectionId');
                        Route::put('sections/{sectionId}', [ClassController::class, 'updateSection'])->whereUuid('sectionId');
                        Route::delete('sections/{sectionId}', [ClassController::class, 'destroySection'])->whereUuid('sectionId');
                    });
            });

        // Parent Routes
        Route::get('all-parents', [\App\Http\Controllers\Api\V1\ParentController::class, 'all']);
        Route::apiResource('parents', \App\Http\Controllers\Api\V1\ParentController::class);

        // Student Routes
        Route::apiResource('students', \App\Http\Controllers\Api\V1\StudentController::class);
        Route::prefix('students/bulk')->group(function () {
            Route::get('template', [StudentBulkUploadController::class, 'template'])->name('students.bulk.template');
            Route::post('preview', [StudentBulkUploadController::class, 'preview'])->name('students.bulk.preview');
        Route::post('{batch}/commit', [StudentBulkUploadController::class, 'commit'])
            ->whereUuid('batch')
            ->name('students.bulk.commit');
        });
        Route::get('students/{student}/results/print', [ResultViewController::class, 'show'])
            ->whereUuid('student');
        Route::get('students/{student}/early-years-report/print', [ResultViewController::class, 'earlyYearsReport'])
            ->whereUuid('student');
        Route::get('results/bulk/print', [ResultViewController::class, 'bulkPrint'])
            ->name('results.bulk.print');
        Route::prefix('students/{student}')
            ->whereUuid('student')
            ->group(function () {
                Route::get('skill-ratings', [StudentSkillRatingController::class, 'index'])
                    ->name('students.skill-ratings.index');
                Route::get('skill-types', [StudentSkillRatingController::class, 'types'])
                    ->name('students.skill-ratings.types');
                Route::post('skill-ratings', [StudentSkillRatingController::class, 'store'])
                    ->name('students.skill-ratings.store');
                Route::put('skill-ratings/{skillRating}', [StudentSkillRatingController::class, 'update'])
                    ->whereUuid('skillRating')
                    ->name('students.skill-ratings.update');
                Route::delete('skill-ratings/{skillRating}', [StudentSkillRatingController::class, 'destroy'])
                    ->whereUuid('skillRating')
                    ->name('students.skill-ratings.destroy');
                Route::get('term-summary', [StudentTermSummaryController::class, 'show'])
                    ->name('students.term-summary.show');
                Route::put('term-summary', [StudentTermSummaryController::class, 'update'])
                    ->name('students.term-summary.update');
                Route::get('result-pins', [ResultPinController::class, 'index'])
                    ->name('students.result-pins.index');
                Route::post('result-pins', [ResultPinController::class, 'store'])
                    ->name('students.result-pins.store');
            });

        Route::prefix('result-pins')->group(function () {
            Route::get('/', [ResultPinController::class, 'indexAll'])
                ->name('result-pins.index');
            Route::post('bulk', [ResultPinController::class, 'bulkGenerate'])
                ->name('result-pins.bulk-generate');
            Route::put('{resultPin}/invalidate', [ResultPinController::class, 'invalidate'])
                ->whereUuid('resultPin')
                ->name('result-pins.invalidate');
            Route::get('cards/print', [ResultPinController::class, 'printCards'])
                ->name('result-pins.cards.print');
        });

        Route::prefix('promotions')->group(function () {
            Route::post('bulk', [PromotionController::class, 'bulk'])
                ->name('promotions.bulk');
            Route::get('history', [PromotionController::class, 'history'])
                ->name('promotions.history');
            Route::get('history/export.pdf', [PromotionController::class, 'exportPdf'])
                ->name('promotions.history.export.pdf');
        });

        Route::get('analytics/academics', [AcademicAnalyticsController::class, 'overview'])
            ->name('analytics.academics');

        Route::prefix('attendance')->group(function () {
            Route::get('students', [StudentAttendanceController::class, 'index'])
                ->name('attendance.students.index');
            Route::post('students', [StudentAttendanceController::class, 'store'])
                ->name('attendance.students.store');
            Route::put('students/{attendance}', [StudentAttendanceController::class, 'update'])
                ->whereUuid('attendance')
                ->name('attendance.students.update');
            Route::delete('students/{attendance}', [StudentAttendanceController::class, 'destroy'])
                ->whereUuid('attendance')
                ->name('attendance.students.destroy');
            Route::get('students/report', [StudentAttendanceController::class, 'report'])
                ->name('attendance.students.report');
            Route::get('students/export.csv', [StudentAttendanceController::class, 'exportCsv'])
                ->name('attendance.students.export.csv');
            Route::get('students/export.pdf', [StudentAttendanceController::class, 'exportPdf'])
                ->name('attendance.students.export.pdf');

            Route::get('staff', [StaffAttendanceController::class, 'index'])
                ->name('attendance.staff.index');
            Route::post('staff', [StaffAttendanceController::class, 'store'])
                ->name('attendance.staff.store');
            Route::put('staff/{staffAttendance}', [StaffAttendanceController::class, 'update'])
                ->whereUuid('staffAttendance')
                ->name('attendance.staff.update');
            Route::delete('staff/{staffAttendance}', [StaffAttendanceController::class, 'destroy'])
                ->whereUuid('staffAttendance')
                ->name('attendance.staff.destroy');
            Route::get('staff/report', [StaffAttendanceController::class, 'report'])
                ->name('attendance.staff.report');
            Route::get('staff/export.csv', [StaffAttendanceController::class, 'exportCsv'])
                ->name('attendance.staff.export.csv');
            Route::get('staff/export.pdf', [StaffAttendanceController::class, 'exportPdf'])
                ->name('attendance.staff.export.pdf');
        });

        // Fee Management Routes
        Route::prefix('fees')->group(function () {
            // Fee Items
            Route::apiResource('items', FeeItemController::class)
                ->parameters(['items' => 'feeItem'])
                ->except(['create', 'edit']);
            
            // Fee Structures
            Route::get('structures/by-session-term', [FeeStructureController::class, 'getBySessionTerm'])
                ->name('fee-structures.by-session-term');

            Route::apiResource('structures', FeeStructureController::class)
                ->parameters(['structures' => 'feeStructure'])
                ->except(['create', 'edit']);

            Route::post('structures/copy', [FeeStructureController::class, 'copy'])
                ->name('fee-structures.copy');
            Route::get('structures/total', [FeeStructureController::class, 'getTotal'])
                ->name('fee-structures.total');
            
            // Bank Details
            Route::apiResource('bank-details', BankDetailController::class)
                ->parameters(['bank-details' => 'bankDetail'])
                ->except(['create', 'edit']);
            Route::put('bank-details/{bankDetail}/set-default', [BankDetailController::class, 'setDefault'])
                ->whereUuid('bankDetail')
                ->name('bank-details.set-default');
            Route::get('bank-details/default/get', [BankDetailController::class, 'getDefault'])
                ->name('bank-details.get-default');
        });

        Route::get('staff/me', [StaffSelfController::class, 'show'])
            ->name('staff.me.show');
        Route::put('staff/me', [StaffSelfController::class, 'update'])
            ->name('staff.me.update');
        Route::get('staff/dashboard', [TeacherDashboardController::class, 'show'])
            ->name('staff.dashboard');

        // Staff Routes
        Route::apiResource('staff', \App\Http\Controllers\Api\V1\StaffController::class);

        // Results
        Route::get('results', [ResultController::class, 'index']);
        Route::post('results/batch', [ResultController::class, 'batchUpsert']);

        // Settings Routes
        Route::prefix('settings')->group(function () {
            Route::get('result-page', [\App\Http\Controllers\Api\V1\ResultPageSettingsController::class, 'show']);
            Route::put('result-page', [\App\Http\Controllers\Api\V1\ResultPageSettingsController::class, 'update']);
            Route::apiResource('subjects', SubjectController::class);
            Route::apiResource('assessment-components', AssessmentComponentController::class)
                ->parameters(['assessment-components' => 'assessmentComponent'])
                ->except(['create', 'edit']);
            
            // Assessment Component Structures
            Route::prefix('assessment-component-structures')->group(function () {
                Route::get('component/{assessmentComponent}', [AssessmentComponentStructureController::class, 'indexByComponent'])
                    ->whereUuid('assessmentComponent')
                    ->name('assessment-component-structures.by-component');
                Route::post('/', [AssessmentComponentStructureController::class, 'store'])
                    ->name('assessment-component-structures.store');
                Route::put('{structure}', [AssessmentComponentStructureController::class, 'update'])
                    ->whereUuid('structure')
                    ->name('assessment-component-structures.update');
                Route::post('bulk', [AssessmentComponentStructureController::class, 'bulkStore'])
                    ->name('assessment-component-structures.bulk-store');
                Route::get('max-score', [AssessmentComponentStructureController::class, 'getMaxScore'])
                    ->name('assessment-component-structures.max-score');
                Route::get('applicable', [AssessmentComponentStructureController::class, 'getApplicable'])
                    ->name('assessment-component-structures.applicable');
                Route::delete('{structure}', [AssessmentComponentStructureController::class, 'destroy'])
                    ->whereUuid('structure')
                    ->name('assessment-component-structures.destroy');
            });

            // CBT assessment links
            Route::prefix('assessment-components/{assessmentComponent}/cbt-links')
                ->whereUuid('assessmentComponent')
                ->group(function () {
                    Route::get('/', [CbtAssessmentLinkController::class, 'index'])
                        ->name('assessment-components.cbt-links.index');
                    Route::post('/', [CbtAssessmentLinkController::class, 'store'])
                        ->name('assessment-components.cbt-links.store');
                });

            Route::prefix('cbt-assessment-links/{linkId}')
                ->whereUuid('linkId')
                ->group(function () {
                    Route::delete('/', [CbtAssessmentLinkController::class, 'destroy'])
                        ->name('cbt-assessment-links.destroy');
                    Route::post('import', [CbtAssessmentLinkController::class, 'importScores'])
                        ->name('cbt-assessment-links.import');
                    Route::get('pending-scores', [CbtAssessmentLinkController::class, 'pendingScores'])
                        ->name('cbt-assessment-links.pending');
                    Route::post('approve', [CbtAssessmentLinkController::class, 'approveScores'])
                        ->name('cbt-assessment-links.approve');
                    Route::post('reject', [CbtAssessmentLinkController::class, 'rejectScores'])
                        ->name('cbt-assessment-links.reject');
                });
            
            Route::apiResource('subject-assignments', SubjectAssignmentController::class)
                ->parameters(['subject-assignments' => 'assignment'])
                ->except(['create', 'edit']);
            Route::apiResource('subject-teacher-assignments', SubjectTeacherAssignmentController::class)
                ->parameters(['subject-teacher-assignments' => 'assignment'])
                ->except(['create', 'edit']);
            Route::apiResource('class-teachers', ClassTeacherAssignmentController::class)
                ->parameters(['class-teachers' => 'classTeacher'])
                ->except(['create', 'edit']);
            Route::apiResource('skill-categories', SkillCategoryController::class)
                ->except(['create', 'edit', 'show']);
            Route::apiResource('skill-types', SkillTypeController::class)
                ->except(['create', 'edit', 'show']);
        });

        Route::prefix('grades')->group(function () {
            Route::get('scales', [GradeScaleController::class, 'index']);
            Route::get('scales/{gradingScale}', [GradeScaleController::class, 'show'])->whereUuid('gradingScale');
            Route::put('scales/{gradingScale}/ranges', [GradeScaleController::class, 'updateRanges'])->whereUuid('gradingScale');
            Route::delete('ranges/{gradeRange}', [GradeScaleController::class, 'destroyRange'])->whereUuid('gradeRange');
            Route::put('scales/{gradingScale}/position-ranges', [GradeScaleController::class, 'updatePositionRanges'])->whereUuid('gradingScale');
            Route::delete('position-ranges/{positionRange}', [GradeScaleController::class, 'destroyPositionRange'])->whereUuid('positionRange');
            Route::put('scales/{gradingScale}/comment-ranges', [GradeScaleController::class, 'updateCommentRanges'])->whereUuid('gradingScale');
            Route::delete('comment-ranges/{commentRange}', [GradeScaleController::class, 'destroyCommentRange'])->whereUuid('commentRange');
        });

        Route::prefix('locations')->group(function () {
            Route::get('countries', [LocationController::class, 'countries']);
            Route::get('states', [LocationController::class, 'states']);
            Route::get('states/{state}/lgas', [LocationController::class, 'lgas'])->whereUuid('state');
            Route::get('blood-groups', [LocationController::class, 'bloodGroups']);
        });

        
    });

    // CBT (Computer-Based Test) Routes
    Route::prefix('cbt')->middleware('auth:sanctum,student')->group(function () {
        // Quiz Management
        Route::apiResource('quizzes', QuizController::class)->parameters(['quizzes' => 'quiz']);
        Route::post('quizzes/{quiz}/publish', [QuizController::class, 'publish'])->whereUuid('quiz');
        Route::post('quizzes/{quiz}/unpublish', [QuizController::class, 'unpublish'])->whereUuid('quiz');
        Route::post('quizzes/{quiz}/close', [QuizController::class, 'close'])->whereUuid('quiz');
        Route::get('quizzes/{quiz}/questions', [QuizController::class, 'getQuestions'])->whereUuid('quiz');

        // Quiz Questions
        Route::post('quizzes/{quiz}/questions', [QuizQuestionController::class, 'store'])->whereUuid('quiz');
        Route::put('quizzes/{quiz}/questions/{question}', [QuizQuestionController::class, 'update'])
            ->whereUuid('quiz')
            ->whereUuid('question');
        Route::delete('quizzes/{quiz}/questions/{question}', [QuizQuestionController::class, 'destroy'])
            ->whereUuid('quiz')
            ->whereUuid('question');
        Route::post('quizzes/{quiz}/questions/reorder', [QuizQuestionController::class, 'reorder'])->whereUuid('quiz');
        Route::post('questions/{question}/options', [QuizQuestionController::class, 'storeOption'])->whereUuid('question');
        Route::put('questions/{question}/options/{option}', [QuizQuestionController::class, 'updateOption'])
            ->whereUuid('question')
            ->whereUuid('option');
        Route::delete('questions/{question}/options/{option}', [QuizQuestionController::class, 'destroyOption'])
            ->whereUuid('question')
            ->whereUuid('option');

        // Quiz Attempts
        Route::apiResource('quiz-attempts', QuizAttemptController::class)
            ->parameters(['quiz-attempts' => 'attempt'])
            ->except(['create', 'edit']);
        Route::post('quiz-attempts/{attempt}/submit', [QuizAttemptController::class, 'submit'])->whereUuid('attempt');
        Route::get('quiz-attempts/history/{user}', [QuizAttemptController::class, 'history'])->whereUuid('user');
        Route::get('quiz-attempts/{attempt}/answers', [QuizAnswerController::class, 'byAttemptDetailed'])
            ->whereUuid('attempt');

        // Quiz Answers
        Route::apiResource('quiz-answers', QuizAnswerController::class)
            ->parameters(['quiz-answers' => 'answer'])
            ->except(['create', 'edit']);

        // Quiz Results
        Route::apiResource('quiz-results', QuizResultController::class)
            ->parameters(['quiz-results' => 'result'])
            ->only(['index', 'show']);
        Route::get('quizzes/{quiz}/results', [QuizResultController::class, 'byQuiz'])->whereUuid('quiz');
        Route::get('quiz-results/{result}/review', [QuizResultController::class, 'review'])->whereUuid('result');
        Route::post('quiz-results/{result}/export', [QuizResultController::class, 'export'])->whereUuid('result');
        Route::get('quiz-results/analytics/performance', [QuizResultController::class, 'analytics']);
    });
});
