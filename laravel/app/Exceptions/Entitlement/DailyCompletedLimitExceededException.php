<?php

namespace App\Exceptions\Entitlement;

use App\Enums\Entitlement\LimitKindEnum;
use App\Enums\Entitlement\TierEnum;
use App\Models\User;

/**
 * Raised when today's completion allowance is spent.
 */
class DailyCompletedLimitExceededException extends DailyLimitExceededException
{
    public static function exceededBy(User $user, TierEnum $tier, int $limit): self
    {
        return new self("User {$user->id} exceeded tier {$tier->value}'s daily completion limit of {$limit}.");
    }

    public function limitKind(): LimitKindEnum
    {
        return LimitKindEnum::COMPLETED;
    }

    public function messageKey(): string
    {
        return 'exceptions.entitlement.daily_completed_limit_reached';
    }
}
