<?php

namespace App\Exceptions\Entitlement;

use RuntimeException;

/**
 * Raised when opening a level would exceed the tier's completion allowance
 * for today (data-model.md R3). Completing a level is never gated (FR-005);
 * this fires on the next open once today's completions already reached the
 * limit.
 */
class DailyCompletedLimitExceededException extends RuntimeException {}
