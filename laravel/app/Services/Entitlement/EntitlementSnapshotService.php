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
 * The schema annotations sit on build() rather than here: the generator hands a
 * docblock's free text to the first annotation inside it that carries no
 * description of its own, so prose and schemas sharing a block put this
 * paragraph into a field. Writing one out by name here would do it too — the
 * generator reads the prose, not just the tags.
 */
class EntitlementSnapshotService
{
    public function __construct(
        private EntitlementService $entitlement,
        private DailyUsageService $usage,
    ) {}

    /**
     * @OA\Schema(
     *     schema="Entitlement.Limits",
     *     type="object",
     *     title="Daily allowances",
     *     description="null means the counter is not enforced — never a large number, so the client branches on it instead of comparing.",
     *
     *     @OA\Property(property="completed", type="integer", nullable=true, example=1),
     *     @OA\Property(property="started", type="integer", nullable=true, example=3)
     * )
     *
     * @OA\Schema(
     *     schema="Entitlement.Tier",
     *     type="object",
     *     title="Catalogue entry",
     *     description="Carries no display name: the client keys into its own billing translations by `key`.",
     *
     *     @OA\Property(property="key", type="string", enum={"free", "plus", "premium", "unlimited"}, example="free"),
     *     @OA\Property(property="limits", ref="#/components/schemas/Entitlement.Limits"),
     *     @OA\Property(property="price_minor", type="integer", description="Monthly price in the payload's currency, minor units, net of VAT.", example=0)
     * )
     *
     * @OA\Schema(
     *     schema="Entitlement.Subscription",
     *     type="object",
     *     title="Subscription state",
     *
     *     @OA\Property(property="status", type="string", enum={"active", "cancelling", "past_due", "ended"}, example="active"),
     *     @OA\Property(property="renews_at", type="string", format="date-time", example="2026-09-23T00:00:00Z"),
     *     @OA\Property(property="pending_tier", type="string", nullable=true, description="Set only while a downgrade is queued.", example="plus"),
     *     @OA\Property(property="pending_tier_effective_at", type="string", format="date-time", nullable=true, example="2026-09-23T00:00:00Z"),
     *     @OA\Property(property="cancel_at_period_end", type="boolean", example=false),
     *     @OA\Property(property="manage_url", type="string", nullable=true, description="The provider's billing portal. Null until a real purchase exists.", example=null)
     * )
     *
     * @OA\Schema(
     *     schema="Entitlement",
     *     type="object",
     *     title="Entitlement state",
     *
     *     @OA\Property(property="tier", type="string", enum={"free", "plus", "premium", "unlimited"}, description="`unlimited` for an exempt account too — they play identically.", example="free"),
     *     @OA\Property(property="is_exempt", type="boolean", description="Unlimited granted without payment. Suppresses every purchase entry point.", example=false),
     *     @OA\Property(property="limits", ref="#/components/schemas/Entitlement.Limits"),
     *     @OA\Property(property="remaining", ref="#/components/schemas/Entitlement.Limits"),
     *     @OA\Property(property="resets_at", type="string", format="date-time", description="UTC. Rendering it in the viewer's time is the client's job.", example="2026-08-24T00:00:00Z"),
     *     @OA\Property(property="purchasing_enabled", type="boolean", example=true),
     *     @OA\Property(property="subscription", ref="#/components/schemas/Entitlement.Subscription", nullable=true),
     *     @OA\Property(property="currency", type="string", description="One currency covers every price, so it sits beside the catalogue rather than inside each entry.", example="EUR"),
     *     @OA\Property(property="tiers", type="array", description="The whole catalogue in ladder order. The client holds no copy of allowances or prices.", @OA\Items(ref="#/components/schemas/Entitlement.Tier"))
     * )
     *
     * @return array<string, mixed>
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
            'currency' => ConfigHelper::getString('billing.currency'),
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
     * @return array<string, mixed>|null
     */
    private function subscription(User $user): ?array
    {
        $subscription = $user->subscription;

        if ($subscription === null) {
            return null;
        }

        $periodEnd = $subscription->current_period_end->toIso8601ZuluString();

        return [
            'status' => $subscription->status->value,
            'renews_at' => $periodEnd,
            'pending_tier' => $subscription->pending_tier?->value,
            // A queued downgrade lands at the period boundary, which is why it
            // has no date column of its own.
            'pending_tier_effective_at' => $subscription->pending_tier === null ? null : $periodEnd,
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
     * @return list<array<string, mixed>>
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
