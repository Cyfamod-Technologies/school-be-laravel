<?php

namespace App\Services\Admin;

use App\Models\AgentPayout;
use App\Services\PayoutService;
use Illuminate\Pagination\LengthAwarePaginator;

class PayoutAdminService
{
    public function __construct(private readonly PayoutService $payoutService)
    {
    }

    public function listPayouts(?string $status = null, ?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $status = trim((string) $status);
        $search = trim((string) $search);

        $payouts = AgentPayout::query()
            ->with(['agent:id,full_name,email,bank_name,bank_account_name,bank_account_number'])
            ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('id', 'like', '%' . $search . '%')
                        ->orWhereHas('agent', function ($agentQuery) use ($search) {
                            $agentQuery
                                ->where('full_name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%')
                                ->orWhere('bank_account_number', 'like', '%' . $search . '%')
                                ->orWhere('bank_name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderByDesc('requested_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $payouts->setCollection(
            $payouts->getCollection()->map(fn (AgentPayout $payout) => $this->formatPayout($payout))
        );

        return $payouts;
    }

    public function getPayoutDetails(AgentPayout $payout): array
    {
        $payout->loadMissing([
            'agent:id,full_name,email,phone,whatsapp_number,bank_name,bank_account_name,bank_account_number',
            'commissions:id,payout_id,commission_amount,status,created_at',
        ]);

        return [
            ...$this->formatPayout($payout),
            'commissions_count' => $payout->commissions->count(),
            'commissions_total' => (float) $payout->commissions->sum('commission_amount'),
            'commissions' => $payout->commissions->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'commission_amount' => (float) $commission->commission_amount,
                    'status' => $commission->status,
                    'created_at' => $commission->created_at,
                ];
            })->values(),
        ];
    }

    public function approvePayout(AgentPayout $payout): array
    {
        $this->payoutService->approvePayout($payout);

        $fresh = $payout->fresh();
        if ($fresh) {
            $payout = $fresh;
        }

        $payout->loadMissing('agent:id,full_name,email,bank_name,bank_account_name,bank_account_number');

        return $this->formatPayout($payout);
    }

    public function processPayout(AgentPayout $payout): array
    {
        $this->payoutService->processPayout($payout);

        $fresh = $payout->fresh();
        if ($fresh) {
            $payout = $fresh;
        }

        $payout->loadMissing('agent:id,full_name,email,bank_name,bank_account_name,bank_account_number');

        return $this->formatPayout($payout);
    }

    public function completePayout(AgentPayout $payout): array
    {
        $this->payoutService->completePayout($payout);

        $fresh = $payout->fresh();
        if ($fresh) {
            $payout = $fresh;
        }

        $payout->loadMissing('agent:id,full_name,email,bank_name,bank_account_name,bank_account_number');

        return $this->formatPayout($payout);
    }

    public function failPayout(AgentPayout $payout, string $reason): array
    {
        $this->payoutService->failPayout($payout, $reason);

        $fresh = $payout->fresh();
        if ($fresh) {
            $payout = $fresh;
        }

        $payout->loadMissing('agent:id,full_name,email,bank_name,bank_account_name,bank_account_number');

        return $this->formatPayout($payout);
    }

    public function updatePayoutStatus(AgentPayout $payout, string $status, ?string $reason = null): array
    {
        $this->payoutService->setPayoutStatus($payout, $status, $reason);

        $fresh = $payout->fresh();
        if ($fresh) {
            $payout = $fresh;
        }

        $payout->loadMissing('agent:id,full_name,email,bank_name,bank_account_name,bank_account_number');

        return $this->formatPayout($payout);
    }

    public function getPayoutStats(): array
    {
        $pendingCount = AgentPayout::query()->where('status', 'pending')->count();
        $approvedCount = AgentPayout::query()->where('status', 'approved')->count();
        $processingCount = AgentPayout::query()->where('status', 'processing')->count();
        $completedCount = AgentPayout::query()->where('status', 'completed')->count();
        $failedCount = AgentPayout::query()->where('status', 'failed')->count();

        $pendingAmount = (float) AgentPayout::query()->where('status', 'pending')->sum('total_amount');
        $approvedAmount = (float) AgentPayout::query()->where('status', 'approved')->sum('total_amount');
        $processingAmount = (float) AgentPayout::query()->where('status', 'processing')->sum('total_amount');
        $completedAmount = (float) AgentPayout::query()->where('status', 'completed')->sum('total_amount');
        $failedAmount = (float) AgentPayout::query()->where('status', 'failed')->sum('total_amount');

        return [
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'processing_count' => $processingCount,
            'completed_count' => $completedCount,
            'failed_count' => $failedCount,
            'pending_amount' => $pendingAmount,
            'approved_amount' => $approvedAmount,
            'processing_amount' => $processingAmount,
            'completed_amount' => $completedAmount,
            'failed_amount' => $failedAmount,
        ];
    }

    private function formatPayout(AgentPayout $payout): array
    {
        $agent = $payout->agent;
        $paymentDetails = $this->decodePaymentDetails($payout->payment_details);

        return [
            'id' => $payout->id,
            'agent_id' => $payout->agent_id,
            'agent_name' => $agent?->full_name,
            'agent_email' => $agent?->email,
            'total_amount' => (float) $payout->total_amount,
            'status' => $payout->status,
            'bank_name' => $paymentDetails['bank_name'] ?? $agent?->bank_name,
            'bank_account_name' => $paymentDetails['account_name'] ?? $agent?->bank_account_name,
            'bank_account_number' => $paymentDetails['account_number'] ?? $agent?->bank_account_number,
            'requested_at' => $payout->requested_at,
            'approved_at' => $payout->approved_at,
            'processed_at' => $payout->processed_at,
            'completed_at' => $payout->completed_at,
            'failure_reason' => $payout->failure_reason,
            'created_at' => $payout->created_at,
            'updated_at' => $payout->updated_at,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function decodePaymentDetails(?string $paymentDetails): array
    {
        if (! is_string($paymentDetails) || $paymentDetails === '') {
            return [];
        }

        $decoded = json_decode($paymentDetails, true);

        return is_array($decoded) ? $decoded : [];
    }
}
