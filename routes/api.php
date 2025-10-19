<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SchoolRegistrationController;
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
use App\Http\Controllers\Api\V1\StudentTermSummaryController;
use App\Http\Controllers\Api\V1\SkillCategoryController;
use App\Http\Controllers\Api\V1\SkillTypeController;
use App\Http\Controllers\ResultViewController;

$host = parse_url(config('app.url'), PHP_URL_HOST);

Route::domain('{subdomain}.' . $host)->group(function () {
    // Add your school-specific routes here
});


Route::get('/migrate', [\App\Http\Controllers\MigrateController::class, 'migrate']);

Route::prefix('api/v1')->group(function () {
    Route::post('/register-school', [SchoolController::class, 'register']);
    Route::post('/login', [SchoolController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [SchoolController::class, 'logout']);
        Route::get('/school', [SchoolController::class, 'showSchoolProfile']);
        Route::put('/school', [SchoolController::class, 'updateSchoolProfile']);
        Route::get('/user', [SchoolController::class, 'showSchoolAdminProfile']);            
        Route::put('/user', [SchoolController::class, 'updateSchoolAdminProfile']);

        // Academic Session Routes
        Route::apiResource('sessions', AcademicSessionController::class);
        Route::get('sessions/{session}/terms', [AcademicSessionController::class, 'getTermsForSession']);
        Route::post('sessions/{session}/terms', [AcademicSessionController::class, 'storeTerm']);
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
        Route::get('students/{student}/results/print', [ResultViewController::class, 'show'])
            ->whereUuid('student');
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
            });

        // Staff Routes
        Route::apiResource('staff', \App\Http\Controllers\Api\V1\StaffController::class);

        // Results
        Route::get('results', [ResultController::class, 'index']);
        Route::post('results/batch', [ResultController::class, 'batchUpsert']);

        // Settings Routes
        Route::prefix('settings')->group(function () {
            Route::apiResource('subjects', SubjectController::class);
            Route::apiResource('assessment-components', AssessmentComponentController::class)
                ->parameters(['assessment-components' => 'assessmentComponent'])
                ->except(['create', 'edit']);
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
        });

        Route::prefix('locations')->group(function () {
            Route::get('countries', [LocationController::class, 'countries']);
            Route::get('states', [LocationController::class, 'states']);
            Route::get('states/{state}/lgas', [LocationController::class, 'lgas'])->whereUuid('state');
            Route::get('blood-groups', [LocationController::class, 'bloodGroups']);
        });
    });
});
