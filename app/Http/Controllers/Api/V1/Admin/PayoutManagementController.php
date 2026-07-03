<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentPayout;
use App\Services\Admin\PayoutAdminService;
use Illuminate\Http\Request;

class PayoutManagementController extends Controller
{
    public function __construct(private readonly PayoutAdminService $payoutAdminService)
    {
    }

    public function index(Request $request)
    {
        $payouts = $this->payoutAdminService->listPayouts(
            status: $request->query('status'),
            search: $request->query('search'),
            perPage: (int) $request->query('per_page', 20)
        );

        $payload = $payouts->toArray();
        $payload['payouts'] = $payload['data'] ?? [];
        $payload['stats'] = $this->payoutAdminService->getPayoutStats();

        return response()->json($payload);
    }

    public function show(string $payout)
    {
        $payoutModel = AgentPayout::findOrFail($payout);

        return response()->json([
            'payout' => $this->payoutAdminService->getPayoutDetails($payoutModel),
        ]);
    }

    public function approve(string $payout)
    {
        $payoutModel = AgentPayout::findOrFail($payout);

        try {
            $updatedPayout = $this->payoutAdminService->approvePayout($payoutModel);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
                'current_status' => $payoutModel->fresh()?->status ?? $payoutModel->status,
            ], 422);
        }

        return response()->json([
            'message' => 'Payout approved successfully.',
            'payout' => $updatedPayout,
        ]);
    }

    public function process(string $payout)
    {
        $payoutModel = AgentPayout::findOrFail($payout);

        try {
            $updatedPayout = $this->payoutAdminService->processPayout($payoutModel);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
                'current_status' => $payoutModel->fresh()?->status ?? $payoutModel->status,
            ], 422);
        }

        return response()->json([
            'message' => 'Payout moved to processing successfully.',
            'payout' => $updatedPayout,
        ]);
    }

    public function complete(string $payout)
    {
        $payoutModel = AgentPayout::findOrFail($payout);

        try {
            $updatedPayout = $this->payoutAdminService->completePayout($payoutModel);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
                'current_status' => $payoutModel->fresh()?->status ?? $payoutModel->status,
            ], 422);
        }

        return response()->json([
            'message' => 'Payout marked as completed successfully.',
            'payout' => $updatedPayout,
        ]);
    }

    public function updateStatus(string $payout, Request $request)
    {
        // Manually resolve the AgentPayout model since route model binding is
        // returning a new empty model for unknown reasons.
        $payoutModel = AgentPayout::findOrFail($payout);

        $validated = $request->validate([
            'status' => 'required|string|in:approved,processing,completed,failed',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $updatedPayout = $this->payoutAdminService->updatePayoutStatus(
                $payoutModel,
                $validated['status'],
                $validated['reason'] ?? null,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
                'current_status' => $payoutModel->fresh()?->status ?? $payoutModel->status,
            ], 422);
        }

        return response()->json([
            'message' => 'Payout status updated successfully.',
            'payout' => $updatedPayout,
        ]);
    }

    public function fail(string $payout, Request $request)
    {
        $payoutModel = AgentPayout::findOrFail($payout);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $updatedPayout = $this->payoutAdminService->failPayout($payoutModel, $validated['reason']);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
                'current_status' => $payoutModel->fresh()?->status ?? $payoutModel->status,
            ], 422);
        }

        return response()->json([
            'message' => 'Payout marked as failed successfully.',
            'payout' => $updatedPayout,
        ]);
    }
}
