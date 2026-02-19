<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\Referral;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    private SubscriptionService $subscriptionService;
    private ReferralService $referralService;

    public function __construct(
        SubscriptionService $subscriptionService,
        ReferralService $referralService
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->referralService = $referralService;
    }

    /**
     * Process commission for referral payment
     */
    public function processCommission(Invoice $invoice, Referral $referral): ?AgentCommission
    {
        // Demo school - no commission
        if ($invoice->school->isDemo()) {
            return null;
        }

        // Check if commission should be triggered
        if (!$this->referralService->shouldTriggerCommission($referral)) {
            return null;
        }

        DB::transaction(function () use ($invoice, $referral) {
            // Calculate commission
            $commissionAmount = $this->subscriptionService->calculateCommission($invoice->total_amount);

            // Create commission record
            $commission = AgentCommission::create([
                'agent_id' => $referral->agent_id,
                'referral_id' => $referral->id,
                'school_id' => $invoice->school_id,
                'invoice_id' => $invoice->id,
                'payment_number' => $referral->payment_count + 1,
                'commission_amount' => $commissionAmount,
                'status' => 'pending',
            ]);

            // Increment payment count
            $this->referralService->incrementPaymentCount($referral);

            // Update referral first payment info if applicable
            if ($referral->payment_count === 1) {
                $referral->update([
                    'first_payment_amount' => $invoice->total_amount,
                    'paid_at' => now(),
                    'status' => 'paid',
                ]);
            }

            return $commission;
        });

        return AgentCommission::where('invoice_id', $invoice->id)
            ->where('referral_id', $referral->id)
            ->first();
    }

    /**
     * Get agent earnings
     */
    public function getAgentEarnings(Agent $agent): array
    {
        $totalPending = $agent->commissions()
            ->where('status', 'pending')
            ->sum('commission_amount');

        $totalApproved = $agent->commissions()
            ->where('status', 'approved')
            ->sum('commission_amount');

        $totalPaid = $agent->commissions()
            ->where('status', 'paid')
            ->sum('commission_amount');

        $totalEarnings = $totalPending + $totalApproved + $totalPaid;
        $minPayoutThreshold = (int) config('commission.min_payout_threshold', 5000);

        return [
            'total' => $totalEarnings,
            'pending' => $totalPending,
            'approved' => $totalApproved,
            'paid' => $totalPaid,
            'available_for_payout' => max(0, $totalApproved - $this->getTotalPendingPayouts($agent)),
            'min_payout_threshold' => $minPayoutThreshold,
            'can_request_payout' => $totalApproved >= $minPayoutThreshold,
        ];
    }

    /**
     * Get commission history
     */
    public function getCommissionHistory(Agent $agent, int $page = 1, int $perPage = 20)
    {
        return $agent->commissions()
            ->with(['referral', 'school'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Approve pending commission
     */
    public function approveCommission(AgentCommission $commission): bool
    {
        return $commission->update(['status' => 'approved']);
    }

    /**
     * Reject commission
     */
    public function rejectCommission(AgentCommission $commission, string $reason): bool
    {
        return $commission->update([
            'status' => 'pending',
            // Store rejection reason in note field if exists, or just keep as pending
        ]);
    }

    /**
     * Get total pending payouts for agent
     */
    private function getTotalPendingPayouts(Agent $agent): float
    {
        return $agent->payouts()
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('total_amount');
    }

    /**
     * Bulk approve commissions
     */
    public function bulkApproveCommissions(array $commissionIds): int
    {
        return AgentCommission::whereIn('id', $commissionIds)
            ->where('status', 'pending')
            ->update(['status' => 'approved']);
    }

    /**
     * Get commissions by status
     */
    public function getCommissionsByStatus(string $status, int $page = 1, int $perPage = 20)
    {
        return AgentCommission::where('status', $status)
            ->with(['agent', 'school', 'referral'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
