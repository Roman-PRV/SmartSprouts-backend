<?php

namespace App\Exceptions\Entitlement;

use RuntimeException;

/**
 * limitKind() is the single source for details.limit_kind (contracts/entitlement-api.md).
 */
abstract class DailyLimitExceededException extends RuntimeException
{
    abstract public function limitKind(): string;
}
