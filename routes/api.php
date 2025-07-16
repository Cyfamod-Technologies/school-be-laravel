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

Route::prefix('api/v1')->group(function () {
    Route::post('/register-school', [SchoolController::class, 'register']);
    Route::post('/login', [SchoolController::class, 'login']);
    Route::middleware('auth:sanctum')->post('/logout', [SchoolController::class, 'logout']);
    Route::middleware('auth:sanctum')->put('/school', [SchoolController::class, 'updateSchoolProfile']);
    Route::middleware('auth:sanctum')->put('/user', [SchoolController::class, 'updatePersonalProfile']);
});
