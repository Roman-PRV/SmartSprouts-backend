<?php

namespace App\Exceptions\Entitlement;

use RuntimeException;

/**
 * limitKind() is the single source for the limit_kind the 403 response carries.
 */
abstract class DailyLimitExceededException extends RuntimeException
{
    abstract public function limitKind(): string;
}
