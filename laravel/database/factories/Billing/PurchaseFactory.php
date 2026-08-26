<?php

namespace Database\Factories\Billing;

use App\Enums\Billing\PurchaseKindEnum;
use App\Enums\Billing\RefundKindEnum;
use App\Enums\Entitlement\TierEnum;
use App\Helpers\ConfigHelper;
use App\Helpers\EmailHasher;
use App\Models\Billing\Purchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * Define the model's default state.
     *
     * A first paid tier, bought and not refunded.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // user_id is declared first on purpose: the hash below reads it
            // once it has already been resolved to an id.
            'user_id' => User::factory(),
            // Derived from the buyer rather than random, so two purchases by
            // one person carry one hash and a test that looks a row up by
            // email finds it — which is the whole point of the column.
            //
            // A buyerless row refuses rather than inventing an address: a hash
            // matching nobody is a row no email lookup can ever reach, and
            // producing one silently would leave the test that needs it green
            // and meaningless.
            'user_email_hash' => function (array $attributes): string {
                if ($attributes['user_id'] === null) {
                    throw new InvalidArgumentException(
                        'A purchase with no buyer still needs the address it was bought with: use the orphaned() state rather than setting user_id to null.',
                    );
                }

                return EmailHasher::of(User::findOrFail($attributes['user_id'])->email);
            },
            'tier' => TierEnum::PLUS,
            'previous_tier' => null,
            'kind' => PurchaseKindEnum::INITIAL,
            // Follows the tier in this same row rather than a constant, so an
            // upgrade fixture is not priced at the tier it upgraded from.
            'amount_minor' => fn (array $attributes): int => $attributes['tier']->priceMinor(),
            'currency' => ConfigHelper::getRequiredString('billing.currency'),
            // Any positive figure will do: the tax is the seller of record's,
            // set from the buyer's country, and nothing here can predict it.
            'tax_minor' => 21,
            'provider_reference' => 'txn_'.fake()->unique()->uuid(),
            'purchased_at' => now(),
            'refunded_at' => null,
            'refund_kind' => null,
        ];
    }

    /**
     * A move up from a tier already held. `previous_tier` is what the refund
     * rules read, so an upgrade without it is not usable test data.
     */
    public function upgrade(TierEnum $from, TierEnum $to): static
    {
        return $this->state(fn (): array => [
            'kind' => PurchaseKindEnum::UPGRADE,
            'previous_tier' => $from,
            'tier' => $to,
        ]);
    }

    /**
     * The buyer's account is gone — the state the six-year retention rule
     * exists for. Pass the address that account used when the test needs to
     * find the row again by hashing it.
     */
    public function orphaned(?string $formerEmail = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'user_email_hash' => EmailHasher::of($formerEmail ?? fake()->safeEmail()),
        ]);
    }

    /**
     * Refunded as a goodwill gesture — the one that consumes the allowance.
     */
    public function refunded(): static
    {
        return $this->state(fn (): array => [
            'refunded_at' => now(),
            'refund_kind' => RefundKindEnum::GOODWILL,
        ]);
    }

    /**
     * Refunded by the seller of record on its own terms, which must leave the
     * goodwill allowance untouched.
     */
    public function refundedByProvider(): static
    {
        return $this->state(fn (): array => [
            'refunded_at' => now(),
            'refund_kind' => RefundKindEnum::PROVIDER,
        ]);
    }
}
