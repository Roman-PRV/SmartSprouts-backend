<?php

namespace App\Services\Entitlement;

use App\Enums\Billing\SubscriptionStatusEnum;
use App\Enums\Entitlement\TierEnum;
use App\Exceptions\Entitlement\TierNotConfiguredException;
use App\Helpers\ConfigHelper;
use App\Models\User;

/**
 * Assembles everything GET /api/entitlement answers: the tier in force, both
 * allowances against today's usage, the subscription, and the tier catalogue.
 *
 * Separate from EntitlementService, which both gates depend on, so that a
 * change to the response shape cannot reach enforcement. Nothing here decides
 * anything — every number comes from the same readers the gates use.
 *
 * The response schemas are annotated on EntitlementController::show(), which
 * references them — not here, so this class stays free of the HTTP contract.
 *
 * @phpstan-type EntitlementSubscriptionShape array{
 *     status: string,
 *     current_period_end: string,
 *     pending_tier: string|null,
 *     cancel_at_period_end: bool,
 *     manage_url: string|null,
 * }
 */
class EntitlementSnapshotService
{
    public function __construct(
        private EntitlementService $entitlement,
        private DailyUsageService $usage,
    ) {}

    /**
     * @return array{
     *     tier: string,
     *     is_exempt: bool,
     *     limits: array{completed: int|null, started: int|null},
     *     remaining: array{completed: int|null, started: int|null},
     *     resets_at: string,
     *     purchasing_enabled: bool,
     *     subscription: EntitlementSubscriptionShape|null,
     *     currency: string,
     *     tiers: list<array{key: string, limits: array{completed: int|null, started: int|null}, price_minor: int}>,
     * }
     *
     * @throws TierNotConfiguredException
     */
    public function build(User $user): array
    {
        $tier = $this->entitlement->resolveTier($user);
        $limits = $tier->limits();
        $used = $this->usage->countsToday($user);

        return [
            'tier' => $tier->value,
            'is_exempt' => $this->entitlement->isExempt($user),
            'limits' => $limits,
            'remaining' => [
                'completed' => $this->remaining($limits['completed'], $used['completed']),
                'started' => $this->remaining($limits['started'], $used['started']),
            ],
            'resets_at' => DailyUsageService::resetsAt()->toIso8601ZuluString(),
            'purchasing_enabled' => ConfigHelper::getBool('billing.purchasing_enabled'),
            'subscription' => $this->subscription($user),
            // Required, not defaulted: the tiers beside it throw when their
            // config is broken, and a price with no currency on screen is worse
            // than an error.
            'currency' => ConfigHelper::getRequiredString('billing.currency'),
            'tiers' => $this->catalogue(),
        ];
    }

    /**
     * Clamped at zero: revoking an exemption mid-day drops an account to Free
     * with more levels already spent than Free allows.
     */
    private function remaining(?int $limit, int $used): ?int
    {
        return $limit === null ? null : max(0, $limit - $used);
    }

    /**
     * Null only for an account that has never subscribed. A closed subscription
     * keeps its row, and its block is still sent beside `tier: "free"` — that is
     * what the client offers to resume.
     *
     * @return EntitlementSubscriptionShape|null
     */
    private function subscription(User $user): ?array
    {
        $subscription = $user->subscription;

        if ($subscription === null) {
            return null;
        }

        return [
            'status' => $subscription->status->value,
            // Not renews_at: only an active subscription renews on this date.
            // A cancelling one ends on it, a past-due one renews nothing until
            // payment recovers, and an ended one has it in the past. Which of
            // those to say is the client's call, from status.
            'current_period_end' => $subscription->current_period_end->toIso8601ZuluString(),
            // A queued downgrade lands at that same boundary, so it needs no
            // date of its own here.
            'pending_tier' => $subscription->pending_tier?->value,
            // From the status, never from cancelled_at: undoing a cancellation
            // returns the subscription to active and leaves that timestamp set.
            'cancel_at_period_end' => $subscription->status === SubscriptionStatusEnum::CANCELLING,
            // BE-09 wires the provider portal. There is no column and no config
            // key for it, and no real purchase exists to link to yet.
            'manage_url' => null,
        ];
    }

    /**
     * Sorted by rank rather than trusting the order the cases are declared in.
     *
     * @return list<array{key: string, limits: array{completed: int|null, started: int|null}, price_minor: int}>
     *
     * @throws TierNotConfiguredException
     */
    private function catalogue(): array
    {
        $tiers = TierEnum::cases();

        usort($tiers, fn (TierEnum $a, TierEnum $b): int => $a->rank() <=> $b->rank());

        return array_map(fn (TierEnum $tier): array => [
            'key' => $tier->value,
            'limits' => $tier->limits(),
            'price_minor' => $tier->priceMinor(),
        ], $tiers);
    }
}
