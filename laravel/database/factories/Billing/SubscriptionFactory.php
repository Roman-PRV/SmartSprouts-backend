<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\SubscriptionStatusEnum;
use App\Enums\Entitlement\TierEnum;
use App\Models\Billing\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * An active paid subscription mid-period. Never Free — a Free account has
     * no row here at all, and a factory that could produce one would let tests
     * assert against a state the application must never create.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tier' => TierEnum::PLUS,
            'pending_tier' => null,
            'status' => SubscriptionStatusEnum::ACTIVE,
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
            'provider_subscription_id' => 'sub_'.fake()->unique()->uuid(),
            'cancelled_at' => null,
        ];
    }

    /**
     * Cancellation requested; the tier still holds until the period ends.
     */
    public function cancelling(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatusEnum::CANCELLING,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * A renewal payment failed; the tier still holds through the grace window.
     */
    public function pastDue(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatusEnum::PAST_DUE,
        ]);
    }

    /**
     * Over — the account resolves to Free from here.
     */
    public function ended(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatusEnum::ENDED,
            'current_period_end' => now()->subDay(),
        ]);
    }

    /**
     * A downgrade queued for the period boundary. The subscription stays
     * active and keeps granting `tier` until then.
     */
    public function pendingDowngradeTo(TierEnum $tier): static
    {
        return $this->state(fn (): array => [
            'pending_tier' => $tier,
        ]);
    }
}
