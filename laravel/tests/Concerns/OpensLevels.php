<?php

namespace Tests\Concerns;

use App\Enums\Entitlement\TierEnum;
use App\Models\Game;
use App\Models\User;
use App\Services\Entitlement\DailyUsageService;

/**
 * Lifts the daily allowances and records level opens, for tests that submit an
 * attempt and are not about entitlement.
 */
trait OpensLevels
{
    /**
     * Laravel calls this for every test class using the trait. Clearing the
     * limits in config is the only thing that reaches both gates: each resolves
     * the account's tier itself, so what openLevel() passes cannot reach the
     * one on submitting.
     */
    protected function setUpOpensLevels(): void
    {
        foreach (TierEnum::cases() as $tier) {
            config()->set("billing.tiers.{$tier->value}.completed_limit", null);
            config()->set("billing.tiers.{$tier->value}.started_limit", null);
        }
    }

    /** Records today's open, the step a real client takes by fetching the level first. */
    protected function openLevel(User $user, Game $game, int $level): void
    {
        app(DailyUsageService::class)->recordOpen($user, TierEnum::UNLIMITED, $game->id, $level);
    }
}
