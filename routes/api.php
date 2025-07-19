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
        Route::apiResource('classes', ClassController::class);
        Route::prefix('classes/{class}')->group(function () {
            Route::get('arms', [ClassController::class, 'indexArms']);
            Route::post('arms', [ClassController::class, 'storeArm']);
            Route::get('arms/{arm}', [ClassController::class, 'showArm']);
            Route::put('arms/{arm}', [ClassController::class, 'updateArm']);
            Route::delete('arms/{arm}', [ClassController::class, 'destroyArm']);

            Route::prefix('arms/{arm}')->group(function () {
                Route::get('sections', [ClassController::class, 'indexSections']);
                Route::post('sections', [ClassController::class, 'storeSection']);
                Route::get('sections/{section}', [ClassController::class, 'showSection']);
                Route::put('sections/{section}', [ClassController::class, 'updateSection']);
                Route::delete('sections/{section}', [ClassController::class, 'destroySection']);
            });
        });
    });
});
