<?php

namespace App\Exceptions\Entitlement;

use App\Enums\Entitlement\TierEnum;
use App\Models\User;
use RuntimeException;

/**
 * limitKind() is the single source for details.limit_kind in the 403
 * response (contracts/entitlement-api.md) — the middleware and Handler.php
 * both read it instead of each mapping the concrete class themselves.
 */
abstract class DailyLimitExceededException extends RuntimeException
{
    abstract public static function exceededBy(User $user, TierEnum $tier, int $limit): static;

    abstract public function limitKind(): string;
}
