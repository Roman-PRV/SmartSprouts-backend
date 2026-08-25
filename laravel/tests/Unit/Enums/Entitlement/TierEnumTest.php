<?php

namespace Tests\Unit\Enums\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Exceptions\TierNotConfiguredException;
use Tests\TestCase;

class TierEnumTest extends TestCase
{
    public function test_there_are_exactly_four_tiers(): void
    {
        $this->assertSame(
            ['free', 'plus', 'premium', 'unlimited'],
            array_column(TierEnum::cases(), 'value'),
        );
    }

    public function test_bounded_tiers_expose_both_allowances(): void
    {
        $this->assertSame(['completed' => 1, 'started' => 3], TierEnum::FREE->limits());
        $this->assertSame(['completed' => 4, 'started' => 8], TierEnum::PLUS->limits());
        $this->assertSame(['completed' => 20, 'started' => 40], TierEnum::PREMIUM->limits());
    }

    public function test_single_accessors_agree_with_the_pair(): void
    {
        $this->assertSame(4, TierEnum::PLUS->completedLimit());
        $this->assertSame(8, TierEnum::PLUS->startedLimit());

        $this->assertNull(TierEnum::UNLIMITED->completedLimit());
        $this->assertNull(TierEnum::UNLIMITED->startedLimit());
    }

    public function test_unlimited_tier_reports_null_limits_rather_than_a_large_number(): void
    {
        $this->assertSame(['completed' => null, 'started' => null], TierEnum::UNLIMITED->limits());
        $this->assertTrue(TierEnum::UNLIMITED->hasNoDailyLimits());
    }

    public function test_bounded_tiers_have_daily_limits(): void
    {
        $this->assertFalse(TierEnum::FREE->hasNoDailyLimits());
        $this->assertFalse(TierEnum::PLUS->hasNoDailyLimits());
        $this->assertFalse(TierEnum::PREMIUM->hasNoDailyLimits());
    }

    /**
     * hasNoDailyLimits() answers for the tier, not for one counter. A tier with
     * one bound and one null answers false while still holding that null, so
     * callers must null-check the counter they are about to compare — `5 >= null`
     * is true in PHP, which would block the account instead of letting it play.
     */
    public function test_half_unbounded_tier_still_holds_a_null(): void
    {
        config(['billing.tiers.premium.completed_limit' => null]);

        $this->assertFalse(TierEnum::PREMIUM->hasNoDailyLimits());
        $this->assertNull(TierEnum::PREMIUM->completedLimit());
        $this->assertSame(40, TierEnum::PREMIUM->startedLimit());
    }

    public function test_free_is_the_default_and_the_only_unpaid_tier(): void
    {
        $this->assertSame(TierEnum::FREE, TierEnum::default());

        $this->assertFalse(TierEnum::FREE->isPaid());
        $this->assertTrue(TierEnum::PLUS->isPaid());
        $this->assertTrue(TierEnum::PREMIUM->isPaid());
        $this->assertTrue(TierEnum::UNLIMITED->isPaid());
    }

    public function test_rank_orders_tiers_ascending(): void
    {
        $this->assertSame(
            [0, 1, 2, 3],
            array_map(static fn (TierEnum $tier): int => $tier->rank(), TierEnum::cases()),
        );
    }

    public function test_upgrade_targets_are_higher_ranked_tiers_in_ladder_order(): void
    {
        $this->assertSame(
            [TierEnum::PLUS, TierEnum::PREMIUM, TierEnum::UNLIMITED],
            TierEnum::FREE->upgradeTargets(),
        );

        $this->assertSame([TierEnum::UNLIMITED], TierEnum::PREMIUM->upgradeTargets());
        $this->assertSame([], TierEnum::UNLIMITED->upgradeTargets());
    }

    /**
     * Rank and price are deliberately independent: a promotional price must not
     * reorder the ladder or move a paid tier out of the checkout paths.
     */
    public function test_a_promotional_price_changes_neither_rank_nor_paid_status(): void
    {
        config(['billing.tiers.premium.price_minor' => 0]);

        $this->assertTrue(TierEnum::PREMIUM->isPaid());
        $this->assertSame(2, TierEnum::PREMIUM->rank());
        $this->assertSame([TierEnum::UNLIMITED], TierEnum::PREMIUM->upgradeTargets());
    }

    public function test_missing_tier_config_throws_instead_of_guessing(): void
    {
        config(['billing.tiers.plus' => null]);

        $this->expectException(TierNotConfiguredException::class);

        TierEnum::PLUS->limits();
    }

    public function test_malformed_limit_throws_rather_than_granting_unlimited_play(): void
    {
        config(['billing.tiers.plus.completed_limit' => 'four']);

        $this->expectException(TierNotConfiguredException::class);

        TierEnum::PLUS->limits();
    }

    public function test_malformed_price_throws(): void
    {
        config(['billing.tiers.plus.price_minor' => '1.00']);

        $this->expectException(TierNotConfiguredException::class);

        TierEnum::PLUS->priceMinor();
    }

    /**
     * `-1` reads as "no limit" in plenty of systems, so someone will eventually
     * write it here. It must not slip through as a negative bound.
     */
    public function test_negative_limit_throws_rather_than_reading_as_unlimited(): void
    {
        config(['billing.tiers.plus.completed_limit' => -1]);

        $this->expectException(TierNotConfiguredException::class);

        TierEnum::PLUS->completedLimit();
    }

    public function test_negative_price_throws(): void
    {
        config(['billing.tiers.plus.price_minor' => -100]);

        $this->expectException(TierNotConfiguredException::class);

        TierEnum::PLUS->priceMinor();
    }

    /**
     * Mirror of the test below: that one checks every case has config, this one
     * that every config entry has a case. An orphan entry is a silent drift from
     * the plan table published in the Terms.
     */
    public function test_config_declares_no_tier_without_a_case(): void
    {
        $this->assertEqualsCanonicalizing(
            array_column(TierEnum::cases(), 'value'),
            array_keys(config('billing.tiers')),
        );
    }

    /**
     * Walks cases() rather than naming tiers, so a fifth tier is exercised the
     * moment it is added. Static analysis already catches a case missing from
     * rank(), but it cannot see config — a tier declared without a config entry
     * would otherwise fail on its first real call rather than on the build.
     */
    public function test_every_tier_is_fully_configured(): void
    {
        $ranks = [];

        foreach (TierEnum::cases() as $tier) {
            // These calls are the assertion: each throws on a missing or
            // malformed entry, so reaching the next line means the tier is sound.
            $tier->completedLimit();
            $tier->startedLimit();

            $this->assertGreaterThanOrEqual(0, $tier->priceMinor());

            $ranks[] = $tier->rank();
        }

        $this->assertSame(range(0, count(TierEnum::cases()) - 1), $ranks);
    }
}
