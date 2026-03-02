<?php

use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AgentManagementController;
use App\Http\Controllers\Api\V1\Admin\PayoutManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::prefix('dashboard')->group(function () {
            Route::get('summary', [AdminDashboardController::class, 'summary'])
                ->name('admin.dashboard.summary');
        });

        Route::get('schools', [AdminDashboardController::class, 'schools'])
            ->name('admin.schools.index');
        Route::get('schools/{school}', [AdminDashboardController::class, 'showSchool'])
            ->whereUuid('school')
            ->name('admin.schools.show');

        Route::prefix('agents')->group(function () {
            Route::get('/', [AgentManagementController::class, 'index'])
                ->name('admin.agents.index');
            Route::get('pending', [AgentManagementController::class, 'pending'])
                ->name('admin.agents.pending');
            Route::get('{agent}', [AgentManagementController::class, 'show'])
                ->whereUuid('agent')
                ->name('admin.agents.show');
            Route::post('{agent}/approve', [AgentManagementController::class, 'approve'])
                ->whereUuid('agent')
                ->name('admin.agents.approve');
            Route::post('{agent}/reject', [AgentManagementController::class, 'reject'])
                ->whereUuid('agent')
                ->name('admin.agents.reject');
            Route::post('{agent}/suspend', [AgentManagementController::class, 'suspend'])
                ->whereUuid('agent')
                ->name('admin.agents.suspend');
        });

        Route::prefix('payouts')->group(function () {
            Route::get('/', [PayoutManagementController::class, 'index'])
                ->name('admin.payouts.index');
            Route::get('{payout}', [PayoutManagementController::class, 'show'])
                ->whereUuid('payout')
                ->name('admin.payouts.show');
            Route::post('{payout}/approve', [PayoutManagementController::class, 'approve'])
                ->whereUuid('payout')
                ->name('admin.payouts.approve');
            Route::post('{payout}/process', [PayoutManagementController::class, 'process'])
                ->whereUuid('payout')
                ->name('admin.payouts.process');
            Route::post('{payout}/complete', [PayoutManagementController::class, 'complete'])
                ->whereUuid('payout')
                ->name('admin.payouts.complete');
            Route::post('{payout}/status', [PayoutManagementController::class, 'updateStatus'])
                ->whereUuid('payout')
                ->name('admin.payouts.status.update');
            Route::post('{payout}/fail', [PayoutManagementController::class, 'fail'])
                ->whereUuid('payout')
                ->name('admin.payouts.fail');
        });
    });
