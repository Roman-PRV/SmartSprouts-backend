<?php

namespace App\Exceptions\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Models\User;

/**
 * Raised when opening a level would push today's distinct opens past the
 * tier's start allowance (data-model.md R3). Never thrown for a tier with no
 * daily limits.
 */
final class DailyStartedLimitExceededException extends DailyLimitExceededException
{
    public static function exceededBy(User $user, TierEnum $tier, int $limit): static
    {
        return new self("User {$user->id} exceeded tier {$tier->value}'s daily start limit of {$limit}.");
    }

    public function limitKind(): string
    {
        return 'started';
    }
}
