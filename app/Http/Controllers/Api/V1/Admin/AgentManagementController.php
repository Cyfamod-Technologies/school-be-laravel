<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\Admin\AgentAdminService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AgentManagementController extends Controller
{
    public function __construct(private readonly AgentAdminService $agentAdminService)
    {
    }

    public function index(Request $request)
    {
        $agents = $this->agentAdminService->listAgents(
            search: $request->query('search'),
            status: $request->query('status'),
            perPage: (int) $request->query('per_page', 20)
        );

        $payload = $agents->toArray();
        $payload['agents'] = $payload['data'] ?? [];

        return response()->json($payload);
    }

    public function show(Agent $agent)
    {
        return response()->json([
            'agent' => $this->agentAdminService->getAgentDetails($agent),
        ]);
    }

    public function pending(Request $request)
    {
        $agents = $this->agentAdminService->pendingAgents(
            perPage: (int) $request->query('per_page', 20)
        );

        $payload = $agents->toArray();
        $payload['agents'] = $payload['data'] ?? [];

        return response()->json($payload);
    }

    public function approve(Agent $agent, Request $request)
    {
        try {
            $agent = $this->agentAdminService->approveAgent($agent, $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Agent approved successfully.',
            'agent' => $agent,
        ]);
    }

    public function reject(Agent $agent, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $agent = $this->agentAdminService->rejectAgent($agent, $validated['reason']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Agent rejected successfully.',
            'agent' => $agent,
        ]);
    }

    public function suspend(Agent $agent, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $agent = $this->agentAdminService->suspendAgent($agent, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Agent suspended successfully.',
            'agent' => $agent,
        ]);
    }

    public function resetPending(Agent $agent)
    {
        $agent = $this->agentAdminService->resetToPending($agent);

        return response()->json([
            'message' => 'Agent moved back to pending review.',
            'agent' => $agent,
        ]);
    }
}
