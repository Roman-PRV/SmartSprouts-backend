<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a tier's entry in config/billing.php is missing or malformed.
 *
 * Thrown rather than defaulted because the two possible defaults are opposite
 * catastrophes: null hands out unlimited play on a typo, zero locks every account
 * out. Neither is safe to guess.
 *
 * Not registered in the Handler — unlike GameNotConfiguredException, which renders
 * as a 400 because the caller asked for something that was never wired up, this is
 * a server fault the caller had no part in, so the framework's 500 is honest.
 */
class TierNotConfiguredException extends RuntimeException {}
