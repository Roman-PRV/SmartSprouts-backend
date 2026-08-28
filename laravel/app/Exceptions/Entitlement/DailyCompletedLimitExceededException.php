<?php

namespace App\Exceptions\Entitlement;

use RuntimeException;

/**
 * Raised when today's completion allowance is spent (data-model.md R3).
 */
class DailyCompletedLimitExceededException extends RuntimeException {}
