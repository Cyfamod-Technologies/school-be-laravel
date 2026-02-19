<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Referral;
use App\Services\ReferralService;
use App\Services\CommissionService;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentController extends Controller
{
    private ReferralService $referralService;
    private CommissionService $commissionService;
    private PayoutService $payoutService;

    public function __construct(
        ReferralService $referralService,
        CommissionService $commissionService,
        PayoutService $payoutService
    ) {
        $this->referralService = $referralService;
        $this->commissionService = $commissionService;
        $this->payoutService = $payoutService;
    }

    /**
     * Register new agent
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:agents',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'required|string|max:20',
            'bank_account_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'company_name' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $agent = Agent::create(array_merge($validated, [
            'status' => 'pending',
        ]));
        $token = $agent->createToken('agent-auth')->plainTextToken;

        return response()->json([
            'message' => 'Agent registration submitted. Awaiting admin approval.',
            'agent' => $agent,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $agent = Agent::where('email', strtolower($validated['email']))->first();

        if (! $agent || ! $agent->password || ! Hash::check($validated['password'], $agent->password)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        $token = $agent->createToken('agent-auth')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'agent' => $agent,
            'token' => $token,
        ]);
    }

    /**
     * Register/Login agent with Google ID token
     */
    public function googleAuth(Request $request)
    {
        $validated = $request->validate([
            'credential' => 'required|string',
        ]);

        try {
            $googleUser = $this->resolveGoogleUser($validated['credential']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'google' => 'Google authentication failed. Please try again.',
            ]);
        }

        $agent = Agent::where('email', $googleUser['email'])->first();

        if (! $agent) {
            $agent = Agent::create([
                'full_name' => $googleUser['name'] ?? 'Google Agent',
                'email' => $googleUser['email'],
                'status' => 'pending',
            ]);
        } elseif (! empty($googleUser['name']) && $agent->full_name !== $googleUser['name']) {
            $agent->update(['full_name' => $googleUser['name']]);
        }

        $token = $agent->createToken('agent-google-auth')->plainTextToken;

        return response()->json([
            'message' => $agent->wasRecentlyCreated
                ? 'Agent registration submitted. Awaiting admin approval.'
                : 'Google sign-in successful.',
            'agent' => $agent,
            'token' => $token,
        ]);
    }

    /**
     * Get agent dashboard
     */
    public function dashboard(Request $request)
    {
        $agent = $this->resolveAgent($request);

        if (!$agent) {
            return response()->json(['message' => 'Agent not found'], 404);
        }

        $referralStats = $this->referralService->getStats($agent);
        $earnings = $this->commissionService->getAgentEarnings($agent);
        $referrals = $agent->referrals()->paginate(10);

        return response()->json([
            'agent' => $agent,
            'referrals' => $referralStats,
            'earnings' => $earnings,
            'recent_referrals' => $referrals,
        ]);
    }

    /**
     * Get authenticated agent profile
     */
    public function profile(Request $request)
    {
        $agent = $this->resolveAgent($request);

        if (! $agent) {
            return response()->json(['message' => 'Agent not found'], 404);
        }

        return response()->json([
            'agent' => $agent,
            'has_password' => ! empty($agent->password),
        ]);
    }

    /**
     * Update authenticated agent profile
     */
    public function updateProfile(Request $request)
    {
        $agent = $this->resolveAgent($request);

        if (! $agent) {
            return response()->json(['message' => 'Agent not found'], 404);
        }

        $validated = $request->validate([
            'full_name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('agents', 'email')->ignore($agent->id),
            ],
            'phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
        ]);

        if (array_key_exists('email', $validated)) {
            $validated['email'] = strtolower((string) $validated['email']);
        }

        $agent->update($validated);
        $updatedAgent = $agent->fresh();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'agent' => $updatedAgent,
            'has_password' => ! empty($updatedAgent->password),
        ]);
    }

    /**
     * Change authenticated agent password
     */
    public function changePassword(Request $request)
    {
        $agent = $this->resolveAgent($request);

        if (! $agent) {
            return response()->json(['message' => 'Agent not found'], 404);
        }

        $hasPassword = ! empty($agent->password);
        $rules = [
            'password' => 'required|string|min:8|confirmed',
        ];

        if ($hasPassword) {
            $rules['current_password'] = 'required|string';
        }

        $validated = $request->validate($rules);

        if ($hasPassword && ! Hash::check($validated['current_password'], (string) $agent->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        if ($hasPassword && Hash::check($validated['password'], (string) $agent->password)) {
            throw ValidationException::withMessages([
                'password' => 'New password must be different from current password.',
            ]);
        }

        $agent->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
            'has_password' => true,
        ]);
    }

    /**
     * Generate referral code and link
     */
    public function generateReferral(Request $request)
    {
        $agent = $this->resolveAgent($request);

        if (!$agent) {
            return response()->json(['message' => 'Agent not found'], 404);
        }

        if (!$agent->isApproved()) {
            return response()->json(['message' => 'Agent not approved'], 403);
        }

        $maxCodes = $this->referralService->getMaxCodesPerAgent();
        $usedCodes = $agent->referrals()->count();
        $remainingCodes = max($maxCodes - $usedCodes, 0);

        if ($remainingCodes <= 0) {
            return response()->json([
                'message' => "Referral code limit reached ({$maxCodes}).",
                'max_referral_codes' => $maxCodes,
                'used_referral_codes' => $usedCodes,
                'remaining_referral_codes' => 0,
            ], 422);
        }

        $validated = $request->validate([
            'custom_code' => 'nullable|string|max:50|unique:referrals,referral_code',
        ]);

        try {
            $referral = $this->referralService->createReferral(
                $agent,
                $validated['custom_code'] ?? null
            );

            return response()->json([
                'message' => 'Referral created successfully',
                'referral' => $referral,
                'max_referral_codes' => $maxCodes,
                'used_referral_codes' => $usedCodes + 1,
                'remaining_referral_codes' => max($remainingCodes - 1, 0),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get commission history
     */
    public function commissionHistory(Request $request)
    {
        $agent = $this->resolveAgent($request);

        if (!$agent) {
            return response()->json(['message' => 'Agent not found'], 404);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        $commissions = $this->commissionService->getCommissionHistory($agent, $page, $perPage);

        return response()->json($commissions);
    }

    /**
     * Request payout
     */
    public function requestPayout(Request $request)
    {
        $agent = $this->resolveAgent($request);

        if (!$agent) {
            return response()->json(['message' => 'Agent not found'], 404);
        }

        try {
            $payout = $this->payoutService->requestPayout($agent);

            return response()->json([
                'message' => 'Payout request created successfully',
                'payout' => $payout,
            ], 201);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'payout' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get payout history
     */
    public function payoutHistory(Request $request)
    {
        $agent = $this->resolveAgent($request);

        if (!$agent) {
            return response()->json(['message' => 'Agent not found'], 404);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        $payouts = $this->payoutService->getPayoutHistory($agent, $page, $perPage);

        return response()->json($payouts);
    }

    /**
     * Get referral details
     */
    public function getReferral(Referral $referral, Request $request)
    {
        $agent = $this->resolveAgent($request);

        if (!$agent || $referral->agent_id !== $agent->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'referral' => $referral->load('school', 'commissions'),
        ]);
    }

    /**
     * Approve agent (admin only)
     */
    public function approveAgent(Agent $agent, Request $request)
    {
        // Check admin authorization
        // TODO: Add proper admin authorization logic

        if (!$agent->isPending()) {
            return response()->json(['message' => 'Agent is not pending approval'], 400);
        }

        $agent->approve(auth()->user()->id ?? 'admin');

        return response()->json([
            'message' => 'Agent approved successfully',
            'agent' => $agent,
        ]);
    }

    /**
     * Reject agent (admin only)
     */
    public function rejectAgent(Agent $agent, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        if (!$agent->isPending()) {
            return response()->json(['message' => 'Agent is not pending approval'], 400);
        }

        $agent->reject($validated['reason']);

        return response()->json([
            'message' => 'Agent rejected',
            'agent' => $agent,
        ]);
    }

    /**
     * List pending agents (admin only)
     */
    public function pendingAgents(Request $request)
    {
        // TODO: Add admin authorization

        $agents = Agent::where('status', 'pending')
            ->paginate($request->input('per_page', 20));

        return response()->json($agents);
    }

    /**
     * Suspend agent (admin only)
     */
    public function suspendAgent(Agent $agent)
    {
        // TODO: Add admin authorization

        $agent->update(['status' => 'suspended']);

        return response()->json([
            'message' => 'Agent suspended',
            'agent' => $agent,
        ]);
    }

    private function resolveAgent(Request $request): ?Agent
    {
        $user = $request->user();
        if ($user instanceof Agent) {
            return $user;
        }

        $agentId = $request->input('agent_id');
        if (is_string($agentId) && $agentId !== '') {
            return Agent::find($agentId);
        }

        return null;
    }

    /**
     * @return array{email:string,name?:string}
     */
    private function resolveGoogleUser(string $credential): array
    {
        $response = Http::timeout(10)
            ->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $credential,
            ]);

        if (! $response->ok()) {
            throw ValidationException::withMessages([
                'google' => 'Invalid Google credential.',
            ]);
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw ValidationException::withMessages([
                'google' => 'Unable to validate Google credential.',
            ]);
        }

        $email = (string) ($data['email'] ?? '');
        if ($email === '') {
            throw ValidationException::withMessages([
                'google' => 'Google account email is missing.',
            ]);
        }

        $verified = filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $verified) {
            throw ValidationException::withMessages([
                'google' => 'Google email must be verified.',
            ]);
        }

        $configuredClientId = (string) config('services.google.client_id');
        if ($configuredClientId !== '' && ($data['aud'] ?? null) !== $configuredClientId) {
            throw ValidationException::withMessages([
                'google' => 'Google client mismatch.',
            ]);
        }

        $result = ['email' => strtolower($email)];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            $result['name'] = $name;
        }

        return $result;
    }
}
