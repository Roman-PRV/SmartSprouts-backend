<?php

namespace App\Exceptions\Entitlement;

use App\Enums\Entitlement\LimitKindEnum;
use RuntimeException;

/**
 * limitKind() is the single source for the limit_kind the 403 response carries.
 */
abstract class DailyLimitExceededException extends RuntimeException
{
    abstract public function limitKind(): LimitKindEnum;

    /**
     * Its own message per limit: the two refusals arrive at different moments
     * and mean different things to the player.
     */
    abstract public function messageKey(): string;
}
