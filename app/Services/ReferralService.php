<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\Referral;
use App\Models\ReferralRegistration;
use App\Models\School;
use Illuminate\Support\Str;

class ReferralService
{
    public function getMaxCodesPerAgent(): int
    {
        $max = (int) config('referral.max_codes_per_agent', 10);
        return $max > 0 ? $max : 10;
    }

    public function getRemainingCodes(Agent $agent): int
    {
        $used = $agent->referrals()->count();
        return max($this->getMaxCodesPerAgent() - $used, 0);
    }

    public function canGenerateCode(Agent $agent): bool
    {
        return $this->getRemainingCodes($agent) > 0;
    }

    /**
     * Generate referral code
     */
    public function generateCode(): string
    {
        do {
            $code = 'AGT-' . strtoupper(Str::random(8));
        } while (Referral::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Generate referral link
     */
    public function generateLink(Agent $agent, string $code): string
    {
        $frontendBase = (string) env('FRONTEND_URL', rtrim((string) config('app.url'), '/'));
        return rtrim($frontendBase, '/') . '/register?ref=' . urlencode($code);
    }

    /**
     * Create referral
     */
    public function createReferral(Agent $agent, ?string $customCode = null): Referral
    {
        if (! $this->canGenerateCode($agent)) {
            throw new \Exception(
                'Referral code limit reached. You cannot generate more referral codes.'
            );
        }

        $code = $customCode ?? $this->generateCode();

        // Validate custom code if provided
        if ($customCode && Referral::where('referral_code', $customCode)->exists()) {
            throw new \Exception('Referral code already exists');
        }

        $referral = Referral::create([
            'agent_id' => $agent->id,
            'referral_code' => $code,
            'referral_link' => $this->generateLink($agent, $code),
            'status' => 'visited',
            'visited_at' => now(),
        ]);

        return $referral;
    }

    /**
     * Find referral by code
     */
    public function findByCode(string $code): ?Referral
    {
        return Referral::where('referral_code', $code)->first();
    }

    /**
     * Record school registration through referral
     */
    public function recordRegistration(Referral $referral, School $school): void
    {
        ReferralRegistration::updateOrCreate(
            ['school_id' => $school->id],
            [
                'referral_id' => $referral->id,
                'registered_at' => now(),
            ]
        );

        $updates = [
            'status' => 'registered',
        ];

        if (! $referral->registered_at) {
            $updates['registered_at'] = now();
        }

        // Keep backward compatibility for old payloads that expect one attached school.
        if (! $referral->school_id) {
            $updates['school_id'] = $school->id;
        }

        $referral->update($updates);
    }

    /**
     * Check if commission should be triggered
     */
    public function shouldTriggerCommission(Referral $referral): bool
    {
        $config = app(SubscriptionService::class)->getCommissionConfig();
        $paymentCount = $config['payment_count'];

        return $referral->payment_count < $paymentCount;
    }

    /**
     * Increment payment count
     */
    public function incrementPaymentCount(Referral $referral): void
    {
        $config = app(SubscriptionService::class)->getCommissionConfig();
        $paymentCount = $config['payment_count'];

        $referral->increment('payment_count');

        if ($referral->payment_count >= $paymentCount) {
            $referral->update(['commission_limit_reached' => true]);
        }
    }

    /**
     * Get referral stats
     */
    public function getStats(Agent $agent): array
    {
        $totalReferrals = $agent->referrals()->count();
        $visitedReferrals = $agent->referrals()->where('status', 'visited')->count();
        $registeredReferrals = $agent->referrals()->where('status', 'registered')->count();
        $paidReferrals = $agent->referrals()->whereIn('status', ['paid', 'active'])->count();
        $agentReferralIds = $agent->referrals()->select('id');
        $registrationsCount = ReferralRegistration::query()
            ->whereIn('referral_id', $agentReferralIds)
            ->count();
        $paidRegistrationsCount = ReferralRegistration::query()
            ->whereIn('referral_id', $agentReferralIds)
            ->where(function ($query) {
                $query
                    ->whereNotNull('paid_at')
                    ->orWhere('payment_count', '>', 0);
            })
            ->count();
        $paidRegistrationsFromBillingCount = ReferralRegistration::query()
            ->whereIn('referral_id', $agentReferralIds)
            ->where(function ($query) {
                $query
                    ->whereNotNull('paid_at')
                    ->orWhere('payment_count', '>', 0)
                    ->orWhereExists(function ($termQuery) {
                        $termQuery
                            ->selectRaw('1')
                            ->from('terms')
                            ->whereColumn('terms.school_id', 'referral_registrations.school_id')
                            ->whereRaw('(COALESCE(terms.amount_paid, 0) + COALESCE(terms.midterm_amount_paid, 0)) > 0');
                    })
                    ->orWhereExists(function ($paymentQuery) {
                        $paymentQuery
                            ->selectRaw('1')
                            ->from('term_payment_transactions')
                            ->whereColumn('term_payment_transactions.school_id', 'referral_registrations.school_id')
                            ->where('status', 'success')
                            ->where('amount', '>', 0);
                    });
            })
            ->count();
        $paidSchoolsTotal = AgentCommission::query()
            ->where('agent_id', $agent->id)
            ->whereNotNull('school_id')
            ->distinct('school_id')
            ->count('school_id');
        $legacyPaidSchoolsCount = Referral::query()
            ->where('agent_id', $agent->id)
            ->whereNotNull('school_id')
            ->whereIn('status', ['paid', 'active'])
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('referral_registrations')
                    ->whereColumn('referral_registrations.referral_id', 'referrals.id')
                    ->whereColumn('referral_registrations.school_id', 'referrals.school_id');
            })
            ->count();
        $legacySchoolsCount = Referral::query()
            ->where('agent_id', $agent->id)
            ->whereNotNull('school_id')
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('referral_registrations')
                    ->whereColumn('referral_registrations.referral_id', 'referrals.id')
                    ->whereColumn('referral_registrations.school_id', 'referrals.school_id');
            })
            ->count();
        $paidSchoolsByRegistration = max($paidRegistrationsCount, $paidRegistrationsFromBillingCount) + $legacyPaidSchoolsCount;
        $paidSchoolsTotal = max($paidSchoolsTotal, $paidSchoolsByRegistration);
        $registeredSchoolsTotal = $registrationsCount + $legacySchoolsCount;
        $maxCodes = $this->getMaxCodesPerAgent();
        $remainingCodes = max($maxCodes - $totalReferrals, 0);

        return [
            'total' => $totalReferrals,
            'visited' => $visitedReferrals,
            'registered' => $registeredReferrals,
            'paid' => $paidReferrals,
            'registered_schools_total' => $registeredSchoolsTotal,
            'paid_schools_total' => $paidSchoolsTotal,
            'conversion_rate' => $registeredSchoolsTotal > 0 ? ($paidSchoolsTotal / $registeredSchoolsTotal) * 100 : 0,
            'max_referral_codes' => $maxCodes,
            'remaining_referral_codes' => $remainingCodes,
            'can_generate_referral' => $remainingCodes > 0,
        ];
    }
}
