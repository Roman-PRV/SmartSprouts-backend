<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Raised when a game's server-side wiring is missing or broken — no service or
 * resource class is mapped for its table_prefix. Rendered as a 400 by the Handler.
 */
class GameNotConfiguredException extends HttpException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(400, $message, $previous, [], $code);
    }
}
