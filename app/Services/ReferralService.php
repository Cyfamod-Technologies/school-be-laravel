<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Referral;
use App\Models\School;
use Illuminate\Support\Str;

class ReferralService
{
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
        $domain = config('app.url');
        return $domain . '/register?ref=' . $code;
    }

    /**
     * Create referral
     */
    public function createReferral(Agent $agent, ?string $customCode = null): Referral
    {
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

        return [
            'total' => $totalReferrals,
            'visited' => $visitedReferrals,
            'registered' => $registeredReferrals,
            'paid' => $paidReferrals,
            'conversion_rate' => $totalReferrals > 0 ? ($paidReferrals / $totalReferrals) * 100 : 0,
        ];
    }
}
