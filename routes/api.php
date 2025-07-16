<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\SchoolRegistrationController;

$host = parse_url(config('app.url'), PHP_URL_HOST);

Route::domain('{subdomain}.' . $host)->group(function () {
    // Add your school-specific routes here
});

Route::post('/register-school', [SchoolRegistrationController::class, 'register']);

Route::get('/migrate', [\App\Http\Controllers\MigrateController::class, 'migrate']);

Route::prefix('school-admin')->group(function () {
    Route::post('/login', [App\Http\Controllers\Api\SchoolAdmin\AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->post('/logout', [App\Http\Controllers\Api\SchoolAdmin\AuthController::class, 'logout']);
});
