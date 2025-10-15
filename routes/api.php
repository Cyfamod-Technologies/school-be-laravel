<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SchoolController;


use App\Http\Controllers\Api\V1\SchoolRegistrationController;

$host = parse_url(config('app.url'), PHP_URL_HOST);

Route::domain('{subdomain}.' . $host)->group(function () {
    // Add your school-specific routes here
});


Route::get('/migrate', [\App\Http\Controllers\MigrateController::class, 'migrate']);

use App\Http\Controllers\Api\V1\AcademicSessionController;
use App\Http\Controllers\Api\V1\ClassController;

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

        // Staff Routes
        Route::apiResource('staff', \App\Http\Controllers\Api\V1\StaffController::class);
    });
});
