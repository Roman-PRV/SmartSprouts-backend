<?php

namespace App\Services\Entitlement;

use App\Enums\Entitlement\ExemptionReasonEnum;
use App\Exceptions\Entitlement\SubscriptionBlocksExemptionException;
use App\Models\Entitlement\AccessExemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grants, revokes and lists free unlimited access.
 */
class ExemptionService
{
    /**
     * Paid Unlimited accounts are absent because they have no row here, not
     * because anything filters them out.
     *
     * @return Collection<int, AccessExemption>
     */
    public function list(): Collection
    {
        return AccessExemption::query()
            ->with(['user', 'grantedBy'])
            ->orderByDesc('granted_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Re-granting overwrites: `user_id` is unique, so a plain insert would fail.
     *
     * @throws SubscriptionBlocksExemptionException when the account is paying
     */
    public function grant(User $user, ExemptionReasonEnum $reason, ?string $note, User $grantedBy): AccessExemption
    {
        $subscription = $user->subscription;

        if ($subscription !== null && $subscription->status->grantsTier()) {
            throw new SubscriptionBlocksExemptionException(
                "User {$user->id} holds a subscription that still grants a tier.",
            );
        }

        $exemption = AccessExemption::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'reason' => $reason,
                'note' => $note,
                'granted_by' => $grantedBy->id,
                'granted_at' => now(),
            ],
        );

        // Keeps a reused $user from answering with the stale relation.
        $user->setRelation('accessExemption', $exemption);

        return $exemption;
    }

    /**
     * @return bool Whether there was anything to revoke.
     */
    public function revoke(User $user): bool
    {
        $exemption = $user->accessExemption;

        if ($exemption === null) {
            return false;
        }

        $exemption->delete();
        $user->setRelation('accessExemption', null);

        return true;
    }
}
