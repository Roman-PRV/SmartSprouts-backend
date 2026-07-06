<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a game's server-side wiring is missing or broken — no service or
 * resource class is mapped for its table_prefix. Rendered as a 400 by the Handler
 * and reported to the log, since it signals a server misconfiguration rather than
 * a client error.
 */
class GameNotConfiguredException extends RuntimeException {}
