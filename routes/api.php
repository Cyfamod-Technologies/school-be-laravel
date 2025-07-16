<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\SchoolRegistrationController;

$host = parse_url(config('app.url'), PHP_URL_HOST);

Route::domain('{subdomain}.' . $host)->group(function () {
    // Add your school-specific routes here
});

use App\Http\Controllers\Api\V1\SchoolController;

Route::post('/register-school', [SchoolRegistrationController::class, 'register']);

Route::get('/migrate', [\App\Http\Controllers\MigrateController::class, 'migrate']);

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::put('/school', [SchoolController::class, 'updateSchoolProfile']);
    Route::put('/user', [SchoolController::class, 'updatePersonalProfile']);
});
