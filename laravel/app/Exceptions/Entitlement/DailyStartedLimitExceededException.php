<?php

namespace App\Exceptions\Entitlement;

use App\Enums\Entitlement\LimitKindEnum;
use App\Enums\Entitlement\TierEnum;
use App\Models\User;

/**
 * Raised when opening a level would push today's distinct opens past the
 * tier's start allowance. Never thrown for a tier with no daily limits.
 */
class DailyStartedLimitExceededException extends DailyLimitExceededException
{
    public static function exceededBy(User $user, TierEnum $tier, int $limit): self
    {
        return new self("User {$user->id} exceeded tier {$tier->value}'s daily start limit of {$limit}.");
    }

    public function limitKind(): LimitKindEnum
    {
        return LimitKindEnum::STARTED;
    }

    public function messageKey(): string
    {
        return 'exceptions.entitlement.daily_started_limit_reached';
    }
}
