<?php

namespace App\Enums\Entitlement;

use App\Exceptions\Entitlement\TierNotConfiguredException;

/**
 * An access tier. The backing value is identity, not a display name — names come
 * from the frontend `billing` i18n namespace, so a rename stays a translation
 * change instead of a data migration plus a reconciliation against names already
 * recorded at the payment provider and in accepted Terms.
 *
 * Limits and price live in config/billing.php under these same keys.
 *
 * There is deliberately no TESTER or STAFF case: unlimited access granted without
 * payment is an account attribute in `access_exemptions`. A fifth case would imply
 * a fifth commercial plan the Terms do not describe, and would leave a revoked
 * exemption with no tier underneath to fall back to.
 */
enum TierEnum: string
{
    case FREE = 'free';
    case PLUS = 'plus';
    case PREMIUM = 'premium';
    case UNLIMITED = 'unlimited';

    /**
     * Every account holds this unless a subscription or exemption grants more.
     */
    public static function default(): self
    {
        return self::FREE;
    }

    /**
     * Position in the tier ladder, ascending. Independent of price on purpose: a
     * promotional price must not be able to turn a higher tier into a lower one.
     *
     * No default arm, so a fifth case fails static analysis instead of silently
     * receiving a wrong rank.
     */
    public function rank(): int
    {
        return match ($this) {
            self::FREE => 0,
            self::PLUS => 1,
            self::PREMIUM => 2,
            self::UNLIMITED => 3,
        };
    }

    /**
     * Null means the counter is not enforced for this tier.
     *
     * @throws TierNotConfiguredException
     */
    public function completedLimit(): ?int
    {
        return $this->limit('completed_limit');
    }

    /**
     * Null means the counter is not enforced for this tier.
     *
     * @throws TierNotConfiguredException
     */
    public function startedLimit(): ?int
    {
        return $this->limit('started_limit');
    }

    /**
     * Both allowances at once, for the tier catalogue the plan page renders.
     * Enforcement asks about one counter at a time and reads that counter's own
     * accessor instead.
     *
     * @return array{completed: int|null, started: int|null}
     *
     * @throws TierNotConfiguredException
     */
    public function limits(): array
    {
        return [
            'completed' => $this->completedLimit(),
            'started' => $this->startedLimit(),
        ];
    }

    /**
     * Not a guard before comparing one counter against its limit — null-check
     * that counter's own accessor instead.
     *
     * @throws TierNotConfiguredException
     */
    public function hasNoDailyLimits(): bool
    {
        return $this->completedLimit() === null && $this->startedLimit() === null;
    }

    /**
     * Monthly price in minor units, net of VAT.
     *
     * @throws TierNotConfiguredException
     */
    public function priceMinor(): int
    {
        $value = $this->setting('price_minor');

        if (! is_int($value) || $value < 0) {
            throw new TierNotConfiguredException("Tier {$this->value} has an invalid price_minor in config/billing.php");
        }

        return $value;
    }

    /**
     * By identity rather than by price, so a promotional price of zero cannot
     * move a paid tier out of the checkout and upgrade paths. Exhaustive for the
     * same reason as rank(): a fifth tier must be decided, not defaulted.
     */
    public function isPaid(): bool
    {
        return match ($this) {
            self::FREE => false,
            self::PLUS, self::PREMIUM, self::UNLIMITED => true,
        };
    }

    /**
     * @return list<self>
     */
    public function upgradeTargets(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $tier): bool => $tier->rank() > $this->rank(),
        ));
    }

    /**
     * See TierNotConfiguredException for why a malformed entry throws instead of
     * falling back to a default.
     *
     * @throws TierNotConfiguredException
     */
    private function limit(string $key): ?int
    {
        $value = $this->setting($key);

        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 0) {
            throw new TierNotConfiguredException("Tier {$this->value} has an invalid {$key} in config/billing.php");
        }

        return $value;
    }

    /**
     * Not `ConfigHelper`: its array readers require every value to be a string,
     * so a tier entry returns the empty default even when it is perfectly valid —
     * the limits are ints and nulls. An empty result would mean "missing",
     * "malformed" and "fine" at once, which is why this reads config directly.
     *
     * @throws TierNotConfiguredException
     */
    private function setting(string $key): mixed
    {
        $entry = config("billing.tiers.{$this->value}");

        if (! is_array($entry)) {
            throw new TierNotConfiguredException("Tier {$this->value} is not defined in config/billing.php");
        }

        if (! array_key_exists($key, $entry)) {
            throw new TierNotConfiguredException("Tier {$this->value} is missing {$key} in config/billing.php");
        }

        return $entry[$key];
    }
}
