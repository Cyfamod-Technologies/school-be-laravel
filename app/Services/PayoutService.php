<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentPayout;
use App\Models\AgentCommission;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    /**
     * Request payout for agent
     */
    public function requestPayout(Agent $agent): ?AgentPayout
    {
        if (! $agent->isApproved()) {
            throw new \Exception('Your account is under review. Payout requests are only available once your account has been approved by an administrator.');
        }

        $minThreshold = (int) config('commission.min_payout_threshold', 5000);

        // Manual commission approval is disabled: convert legacy pending records.
        AgentCommission::query()
            ->where('agent_id', $agent->id)
            ->where('status', 'pending')
            ->whereNull('payout_id')
            ->update(['status' => 'approved']);

        $existingOpenPayout = $agent->payouts()
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->exists();

        if ($existingOpenPayout) {
            throw new \Exception('You already have a payout request in progress.');
        }

        // Get approved commissions not yet paid out
        $approvedCommissions = $agent->commissions()
            ->where('status', 'approved')
            ->whereNull('payout_id')
            ->get();

        $totalAmount = (float) $approvedCommissions->sum('commission_amount');

        // Check if meets minimum threshold
        if ($totalAmount < $minThreshold) {
            throw new \Exception(
                'Minimum payout threshold not met. Required: ₦'
                . number_format($minThreshold, 2)
                . ', Available: ₦'
                . number_format($totalAmount, 2)
            );
        }

        return DB::transaction(function () use ($agent, $approvedCommissions, $totalAmount) {
            // Create payout request
            $payout = AgentPayout::create([
                'agent_id' => $agent->id,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_details' => json_encode([
                    'bank_name' => $agent->bank_name,
                    'account_name' => $agent->bank_account_name,
                    'account_number' => $agent->bank_account_number,
                ]),
                'requested_at' => now(),
            ]);

            // Link commissions to payout
            $approvedCommissions->each(function (AgentCommission $commission) use ($payout) {
                $commission->update(['payout_id' => $payout->id]);
            });

            return $payout;
        });
    }

    /**
     * Get payout history for agent
     */
    public function getPayoutHistory(Agent $agent, int $page = 1, int $perPage = 20)
    {
        return $agent->payouts()
            ->orderBy('requested_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Approve payout
     */
    public function approvePayout(AgentPayout $payout): bool
    {
        $status = $this->normalizeStatus($payout->status);

        if (in_array($status, ['approved', 'processing', 'completed'], true)) {
            return true;
        }

        if ($status !== 'pending') {
            throw new \Exception('Only pending payouts can be approved. Current status: ' . ($status !== '' ? $status : 'unknown'));
        }

        return $payout->approve();
    }

    /**
     * Process payout (mark as processing)
     */
    public function processPayout(AgentPayout $payout): bool
    {
        $status = $this->normalizeStatus($payout->status);

        if (in_array($status, ['processing', 'completed'], true)) {
            return true;
        }

        if ($status !== 'approved') {
            throw new \Exception('Only approved payouts can be processed. Current status: ' . ($status !== '' ? $status : 'unknown'));
        }

        return $payout->markAsProcessing();
    }

    /**
     * Complete payout
     */
    public function completePayout(AgentPayout $payout): bool
    {
        $status = $this->normalizeStatus($payout->status);

        if ($status === 'completed') {
            return true;
        }

        if ($status !== 'processing') {
            throw new \Exception('Only processing payouts can be completed. Current status: ' . ($status !== '' ? $status : 'unknown'));
        }

        return DB::transaction(function () use ($payout) {
            $completed = $payout->complete();

            if (! $completed) {
                return false;
            }

            AgentCommission::query()
                ->where('payout_id', $payout->id)
                ->update(['status' => 'paid']);

            return true;
        });
    }

    /**
     * Admin override to set payout status directly.
     */
    public function setPayoutStatus(AgentPayout $payout, string $targetStatus, ?string $reason = null): bool
    {
        $target = $this->normalizeStatus($targetStatus);
        $current = $this->normalizeStatus($payout->status);
        $allowed = ['approved', 'processing', 'completed', 'failed'];

        if (! in_array($target, $allowed, true)) {
            throw new \Exception('Invalid payout status: ' . $targetStatus);
        }

        if ($target === $current) {
            return true;
        }

        return DB::transaction(function () use ($payout, $target, $current, $reason) {
            if ($target === 'approved') {
                $updated = $payout->approve();

                if ($updated && $current === 'completed') {
                    AgentCommission::query()
                        ->where('payout_id', $payout->id)
                        ->where('status', 'paid')
                        ->update(['status' => 'approved']);
                }

                return $updated;
            }

            if ($target === 'processing') {
                $updated = $payout->markAsProcessing();

                if ($updated && $current === 'completed') {
                    AgentCommission::query()
                        ->where('payout_id', $payout->id)
                        ->where('status', 'paid')
                        ->update(['status' => 'approved']);
                }

                return $updated;
            }

            if ($target === 'completed') {
                $updated = $payout->complete();

                if (! $updated) {
                    return false;
                }

                AgentCommission::query()
                    ->where('payout_id', $payout->id)
                    ->update(['status' => 'paid']);

                return true;
            }

            $failureReason = trim((string) $reason);
            if ($failureReason === '') {
                $failureReason = 'Marked as failed by admin.';
            }

            AgentCommission::query()
                ->where('payout_id', $payout->id)
                ->update(['payout_id' => null, 'status' => 'approved']);

            return $payout->fail($failureReason);
        });
    }

    private function normalizeStatus(?string $status): string
    {
        return strtolower(trim((string) $status));
    }

    /**
     * Fail payout
     */
    public function failPayout(AgentPayout $payout, string $reason): bool
    {
        // Revert commission links
        AgentCommission::where('payout_id', $payout->id)
            ->update(['payout_id' => null, 'status' => 'approved']);

        return $payout->fail($reason);
    }

    /**
     * Bulk approve payouts
     */
    public function bulkApprovePayout(array $payoutIds): int
    {
        return AgentPayout::whereIn('id', $payoutIds)
            ->where('status', 'pending')
            ->update(['approved_at' => now(), 'status' => 'approved']);
    }

    /**
     * Bulk process payouts
     */
    public function bulkProcessPayouts(array $payoutIds): int
    {
        return AgentPayout::whereIn('id', $payoutIds)
            ->where('status', 'approved')
            ->update(['processed_at' => now(), 'status' => 'processing']);
    }

    /**
     * Get pending payouts
     */
    public function getPendingPayouts(int $page = 1, int $perPage = 20)
    {
        return AgentPayout::where('status', 'pending')
            ->with('agent')
            ->orderBy('requested_at', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get payout statistics
     */
    public function getPayoutStats(): array
    {
        $pending = AgentPayout::where('status', 'pending')->sum('total_amount');
        $approved = AgentPayout::where('status', 'approved')->sum('total_amount');
        $processing = AgentPayout::where('status', 'processing')->sum('total_amount');
        $completed = AgentPayout::where('status', 'completed')->sum('total_amount');
        $failed = AgentPayout::where('status', 'failed')->sum('total_amount');

        return [
            'pending' => $pending,
            'approved' => $approved,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'total_completed' => $completed,
        ];
    }
}
