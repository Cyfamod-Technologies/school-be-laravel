<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Referral;
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
        $referral->update([
            'school_id' => $school->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
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
        $maxCodes = $this->getMaxCodesPerAgent();
        $remainingCodes = max($maxCodes - $totalReferrals, 0);

        return [
            'total' => $totalReferrals,
            'visited' => $visitedReferrals,
            'registered' => $registeredReferrals,
            'paid' => $paidReferrals,
            'conversion_rate' => $totalReferrals > 0 ? ($paidReferrals / $totalReferrals) * 100 : 0,
            'max_referral_codes' => $maxCodes,
            'remaining_referral_codes' => $remainingCodes,
            'can_generate_referral' => $remainingCodes > 0,
        ];
    }
}
