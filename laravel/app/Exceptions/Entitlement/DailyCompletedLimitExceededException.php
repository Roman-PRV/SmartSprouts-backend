<?php

namespace App\Exceptions\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Models\User;

/**
 * Raised when today's completion allowance is spent (data-model.md R3).
 */
final class DailyCompletedLimitExceededException extends DailyLimitExceededException
{
    public static function exceededBy(User $user, TierEnum $tier, int $limit): static
    {
        return new self("User {$user->id} exceeded tier {$tier->value}'s daily completion limit of {$limit}.");
    }

    public function limitKind(): string
    {
        return 'completed';
    }
}
