<?php

namespace App\Exceptions\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Models\User;
use RuntimeException;

/**
 * limitKind() is the single source for details.limit_kind (contracts/entitlement-api.md).
 */
abstract class DailyLimitExceededException extends RuntimeException
{
    abstract public static function exceededBy(User $user, TierEnum $tier, int $limit): static;

    abstract public function limitKind(): string;
}
