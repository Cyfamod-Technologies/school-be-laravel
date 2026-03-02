<?php

namespace App\Services\Admin;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\AgentPayout;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class AgentAdminService
{
    public function listAgents(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $search = trim((string) $search);
        $status = trim((string) $status);

        $agents = Agent::query()
            ->withCount(['referrals', 'commissions', 'payouts'])
            ->withSum('commissions as total_commissions_amount', 'commission_amount')
            ->withSum([
                'commissions as approved_commissions_amount' => fn ($query) => $query->where('status', 'approved'),
            ], 'commission_amount')
            ->withSum([
                'commissions as paid_commissions_amount' => fn ($query) => $query->where('status', 'paid'),
            ], 'commission_amount')
            ->withSum([
                'payouts as completed_payout_amount' => fn ($query) => $query->where('status', 'completed'),
            ], 'total_amount')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('full_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('company_name', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $agents->setCollection(
            $agents->getCollection()->map(fn (Agent $agent) => $this->formatAgent($agent))
        );

        return $agents;
    }

    public function pendingAgents(int $perPage = 15): LengthAwarePaginator
    {
        return $this->listAgents(status: 'pending', perPage: $perPage);
    }

    public function getAgentDetails(Agent $agent): array
    {
        $agent->loadCount(['referrals', 'commissions', 'payouts']);

        $totalCommissions = (float) AgentCommission::query()
            ->where('agent_id', $agent->id)
            ->sum('commission_amount');
        $approvedCommissions = (float) AgentCommission::query()
            ->where('agent_id', $agent->id)
            ->where('status', 'approved')
            ->sum('commission_amount');
        $paidCommissions = (float) AgentCommission::query()
            ->where('agent_id', $agent->id)
            ->where('status', 'paid')
            ->sum('commission_amount');
        $completedPayouts = (float) AgentPayout::query()
            ->where('agent_id', $agent->id)
            ->where('status', 'completed')
            ->sum('total_amount');

        $recentReferrals = $agent->referrals()
            ->withCount('registrations')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function ($referral) {
                return [
                    'id' => $referral->id,
                    'referral_code' => $referral->referral_code,
                    'status' => $referral->status,
                    'payment_count' => (int) ($referral->payment_count ?? 0),
                    'registered_schools_count' => (int) ($referral->registrations_count ?? 0),
                    'created_at' => $referral->created_at,
                ];
            })
            ->values();

        $recentPayouts = $agent->payouts()
            ->orderByDesc('requested_at')
            ->limit(8)
            ->get()
            ->map(function (AgentPayout $payout) {
                return [
                    'id' => $payout->id,
                    'total_amount' => (float) $payout->total_amount,
                    'status' => $payout->status,
                    'requested_at' => $payout->requested_at,
                    'approved_at' => $payout->approved_at,
                    'processed_at' => $payout->processed_at,
                    'completed_at' => $payout->completed_at,
                ];
            })
            ->values();

        return [
            ...$this->formatAgent($agent),
            'commission_summary' => [
                'total' => $totalCommissions,
                'approved' => $approvedCommissions,
                'paid' => $paidCommissions,
                'available_for_payout' => max(0, $approvedCommissions),
            ],
            'payout_summary' => [
                'completed_total' => $completedPayouts,
            ],
            'recent_referrals' => $recentReferrals,
            'recent_payouts' => $recentPayouts,
        ];
    }

    public function approveAgent(Agent $agent, ?User $approvedBy = null): Agent
    {
        if (! $agent->isPending()) {
            throw new \InvalidArgumentException('Agent is not pending approval.');
        }

        $agent->approve($approvedBy?->id ?? 'admin');

        return $agent->fresh() ?? $agent;
    }

    public function rejectAgent(Agent $agent, string $reason): Agent
    {
        if (! $agent->isPending()) {
            throw new \InvalidArgumentException('Agent is not pending approval.');
        }

        $agent->reject($reason);

        return $agent->fresh() ?? $agent;
    }

    public function suspendAgent(Agent $agent, ?string $reason = null): Agent
    {
        $agent->forceFill([
            'status' => 'suspended',
            'rejection_reason' => $reason ?: $agent->rejection_reason,
        ])->save();

        return $agent->fresh() ?? $agent;
    }

    private function formatAgent(Agent $agent): array
    {
        return [
            'id' => $agent->id,
            'full_name' => $agent->full_name,
            'email' => $agent->email,
            'phone' => $agent->phone,
            'whatsapp_number' => $agent->whatsapp_number,
            'company_name' => $agent->company_name,
            'address' => $agent->address,
            'status' => $agent->status,
            'email_verified_at' => $agent->email_verified_at,
            'approved_at' => $agent->approved_at,
            'rejection_reason' => $agent->rejection_reason,
            'bank_name' => $agent->bank_name,
            'bank_account_name' => $agent->bank_account_name,
            'bank_account_number' => $agent->bank_account_number,
            'referrals_count' => (int) ($agent->referrals_count ?? 0),
            'commissions_count' => (int) ($agent->commissions_count ?? 0),
            'payouts_count' => (int) ($agent->payouts_count ?? 0),
            'total_commissions_amount' => (float) ($agent->total_commissions_amount ?? 0),
            'approved_commissions_amount' => (float) ($agent->approved_commissions_amount ?? 0),
            'paid_commissions_amount' => (float) ($agent->paid_commissions_amount ?? 0),
            'completed_payout_amount' => (float) ($agent->completed_payout_amount ?? 0),
            'created_at' => $agent->created_at,
            'updated_at' => $agent->updated_at,
        ];
    }
}
