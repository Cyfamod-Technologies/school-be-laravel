<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\Referral;
use App\Models\ReferralRegistration;
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
        $invoice->loadMissing(['school', 'term']);

        // Demo school - no commission
        if (! $invoice->school || $invoice->school->isDemo()) {
            return null;
        }

        $commissionableAmount = $this->resolveCommissionablePaymentAmount($invoice);
        if ($commissionableAmount <= 0) {
            return null;
        }

        $existingCommission = AgentCommission::query()
            ->where('referral_id', $referral->id)
            ->where('invoice_id', $invoice->id)
            ->first();
        if ($existingCommission) {
            $updatedAmount = $this->subscriptionService->calculateCommission($commissionableAmount);
            if (abs((float) ($existingCommission->commission_amount ?? 0) - $updatedAmount) >= 0.01) {
                $existingCommission->update([
                    'commission_amount' => $updatedAmount,
                ]);
            }

            $this->syncPaymentSnapshot($referral, $invoice, $commissionableAmount);

            return $existingCommission->fresh();
        }

        $paymentLimit = max(1, (int) ($this->subscriptionService->getCommissionConfig()['payment_count'] ?? 1));
        $registration = ReferralRegistration::query()
            ->where('referral_id', $referral->id)
            ->where('school_id', $invoice->school_id)
            ->first();

        if ($registration) {
            if ((int) ($registration->payment_count ?? 0) >= $paymentLimit) {
                return null;
            }
        } elseif (! $this->referralService->shouldTriggerCommission($referral)) {
            // Legacy fallback when registration record is missing.
            return null;
        }

        return DB::transaction(function () use ($invoice, $referral) {
            $freshReferral = $referral->fresh();
            if (! $freshReferral) {
                return AgentCommission::query()
                    ->where('referral_id', $referral->id)
                    ->where('invoice_id', $invoice->id)
                    ->first();
            }

            $freshRegistration = ReferralRegistration::query()
                ->where('referral_id', $freshReferral->id)
                ->where('school_id', $invoice->school_id)
                ->lockForUpdate()
                ->first();
            $paymentLimit = max(1, (int) ($this->subscriptionService->getCommissionConfig()['payment_count'] ?? 1));
            $currentPaymentCount = $freshRegistration
                ? (int) ($freshRegistration->payment_count ?? 0)
                : (int) ($freshReferral->payment_count ?? 0);

            if ($currentPaymentCount >= $paymentLimit) {
                return AgentCommission::query()
                    ->where('referral_id', $referral->id)
                    ->where('invoice_id', $invoice->id)
                    ->first();
            }

            $lockedInvoice = Invoice::query()
                ->with(['school', 'term'])
                ->where('id', $invoice->id)
                ->lockForUpdate()
                ->first();
            if (! $lockedInvoice || ! $lockedInvoice->school || $lockedInvoice->school->isDemo()) {
                return null;
            }

            $commissionableAmount = $this->resolveCommissionablePaymentAmount($lockedInvoice);
            if ($commissionableAmount <= 0) {
                return null;
            }

            $existingCommission = AgentCommission::query()
                ->where('referral_id', $freshReferral->id)
                ->where('invoice_id', $lockedInvoice->id)
                ->lockForUpdate()
                ->first();
            if ($existingCommission) {
                $updatedAmount = $this->subscriptionService->calculateCommission($commissionableAmount);
                if (abs((float) ($existingCommission->commission_amount ?? 0) - $updatedAmount) >= 0.01) {
                    $existingCommission->update([
                        'commission_amount' => $updatedAmount,
                    ]);
                }
                $this->syncPaymentSnapshot($freshReferral, $lockedInvoice, $commissionableAmount);

                return $existingCommission->fresh();
            }

            // Calculate commission
            $commissionAmount = $this->subscriptionService->calculateCommission($commissionableAmount);

            // Create commission record
            $commission = AgentCommission::create([
                'agent_id' => $freshReferral->agent_id,
                'referral_id' => $freshReferral->id,
                'school_id' => $lockedInvoice->school_id,
                'invoice_id' => $lockedInvoice->id,
                'payment_number' => $currentPaymentCount + 1,
                'commission_amount' => $commissionAmount,
                'status' => 'pending',
            ]);

            if ($freshRegistration) {
                $registrationUpdates = [
                    'payment_count' => $currentPaymentCount + 1,
                    'active_at' => now(),
                ];
                if ($currentPaymentCount === 0) {
                    $registrationUpdates['first_payment_amount'] = $commissionableAmount;
                    $registrationUpdates['paid_at'] = now();
                }
                $freshRegistration->update($registrationUpdates);
            } else {
                // Legacy fallback only.
                $this->referralService->incrementPaymentCount($freshReferral);
            }

            $referralStatus = strtolower((string) ($freshReferral->status ?? ''));
            if (! in_array($referralStatus, ['paid', 'active'], true) || ! $freshReferral->paid_at) {
                $referralUpdates = [
                    'status' => in_array($referralStatus, ['paid', 'active'], true) ? $freshReferral->status : 'paid',
                    'paid_at' => $freshReferral->paid_at ?? now(),
                ];
                if ((float) ($freshReferral->first_payment_amount ?? 0) < $commissionableAmount) {
                    $referralUpdates['first_payment_amount'] = $commissionableAmount;
                }
                $freshReferral->update($referralUpdates);
            }

            return $commission;
        });
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

    /**
     * Backfill missing commissions for already-paid schools tied to an agent's referrals.
     */
    public function reconcileAgentCommissions(Agent $agent): int
    {
        $created = 0;

        $referrals = $agent->referrals()
            ->with('registrations')
            ->get();

        foreach ($referrals as $referral) {
            $schoolIds = $referral->registrations
                ->pluck('school_id')
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->unique()
                ->values();

            if (
                is_string($referral->school_id)
                && $referral->school_id !== ''
                && ! $schoolIds->contains($referral->school_id)
            ) {
                $schoolIds->push($referral->school_id);
            }

            foreach ($schoolIds as $schoolId) {
                $paidInvoices = Invoice::query()
                    ->with('school')
                    ->where('school_id', $schoolId)
                    ->where('invoice_type', 'original')
                    ->where('total_amount', '>', 0)
                    ->where(function ($query) {
                        $query
                            ->whereIn('status', ['paid', 'partial'])
                            ->orWhereNotNull('paid_at');
                    })
                    ->orderBy('created_at')
                    ->get();

                foreach ($paidInvoices as $invoice) {
                    $alreadyExists = AgentCommission::query()
                        ->where('referral_id', $referral->id)
                        ->where('invoice_id', $invoice->id)
                        ->exists();

                    $commission = $this->processCommission($invoice, $referral);
                    if ($commission && ! $alreadyExists) {
                        $created++;
                    }
                }
            }
        }

        return $created;
    }

    private function resolveCommissionablePaymentAmount(Invoice $invoice): float
    {
        $invoice->loadMissing('term');

        $term = $invoice->term;
        if ($term) {
            $termTotalPaid = (float) (($term->amount_paid ?? 0) + ($term->midterm_amount_paid ?? 0));
            if ($termTotalPaid > 0) {
                return max(0, $termTotalPaid);
            }
        }

        return max(0, (float) ($invoice->total_amount ?? 0));
    }

    private function syncPaymentSnapshot(Referral $referral, Invoice $invoice, float $paymentAmount): void
    {
        $registration = ReferralRegistration::query()
            ->where('referral_id', $referral->id)
            ->where('school_id', $invoice->school_id)
            ->first();

        if ($registration) {
            $registrationUpdates = [];
            if (! $registration->paid_at) {
                $registrationUpdates['paid_at'] = now();
            }
            if ((float) ($registration->first_payment_amount ?? 0) < $paymentAmount) {
                $registrationUpdates['first_payment_amount'] = $paymentAmount;
            }
            if ($registrationUpdates !== []) {
                $registration->update($registrationUpdates);
            }
        }

        $referralStatus = strtolower((string) ($referral->status ?? ''));
        $referralUpdates = [];
        if (! in_array($referralStatus, ['paid', 'active'], true)) {
            $referralUpdates['status'] = 'paid';
        }
        if (! $referral->paid_at) {
            $referralUpdates['paid_at'] = now();
        }
        if ((float) ($referral->first_payment_amount ?? 0) < $paymentAmount) {
            $referralUpdates['first_payment_amount'] = $paymentAmount;
        }
        if ($referralUpdates !== []) {
            $referral->update($referralUpdates);
        }
    }
}
