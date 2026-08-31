<?php

namespace Tests\Concerns;

use App\Enums\Entitlement\TierEnum;
use App\Models\Game;
use App\Models\User;
use App\Services\Entitlement\DailyUsageService;

/**
 * For tests that submit an attempt without being about the allowances.
 */
trait OpensLevels
{
    /**
     * Records today's open, the step a real client takes by fetching the level
     * before submitting. Unlimited on purpose: this only satisfies the submit
     * gate's precondition, and the allowances themselves are covered under
     * tests/Feature/Entitlement.
     */
    protected function openLevel(User $user, Game $game, int $level): void
    {
        app(DailyUsageService::class)->recordOpen($user, TierEnum::UNLIMITED, $game->id, $level);
    }
}
