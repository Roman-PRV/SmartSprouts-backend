<?php

namespace App\Exceptions\Entitlement;

use RuntimeException;

/**
 * Raised when opening a level would push today's distinct opens past the
 * tier's start allowance (data-model.md R3). Never thrown for a tier with no
 * daily limits.
 */
class DailyStartedLimitExceededException extends RuntimeException {}
