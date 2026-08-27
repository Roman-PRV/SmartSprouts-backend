<?php

namespace App\Services\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Models\User;

/**
 * Resolves the tier in force today: exemption first, then subscription, else Free.
 */
class EntitlementService
{
    /**
     * Reads `tier`, never `pending_tier` — a queued downgrade lands at the
     * period boundary, not today.
     */
    public function resolveTier(User $user): TierEnum
    {
        if ($this->isExempt($user)) {
            return TierEnum::UNLIMITED;
        }

        return $this->subscribedTier($user) ?? TierEnum::default();
    }

    /**
     * Free grant rather than a purchase. Both resolve to Unlimited, so this is
     * the only thing that tells them apart.
     */
    public function isExempt(User $user): bool
    {
        return $user->accessExemption !== null;
    }

    /**
     * Null when there is no subscription, or it no longer grants a tier.
     */
    private function subscribedTier(User $user): ?TierEnum
    {
        $subscription = $user->subscription;

        if ($subscription === null || ! $subscription->status->grantsTier()) {
            return null;
        }

        return $subscription->tier;
    }
}
