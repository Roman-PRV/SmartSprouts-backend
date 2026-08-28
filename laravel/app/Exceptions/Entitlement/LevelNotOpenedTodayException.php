<?php

namespace App\Exceptions\Entitlement;

use RuntimeException;

/**
 * Raised when a completion is submitted for a level with no row for today —
 * a count against an absent row is indistinguishable from one under any
 * limit, so the absence must be checked for directly.
 */
class LevelNotOpenedTodayException extends RuntimeException {}
