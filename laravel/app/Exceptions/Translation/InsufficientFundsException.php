<?php

namespace App\Exceptions\Translation;

use Exception;

class InsufficientFundsException extends TranslationFailedException
{
    /**
     * @param  string|null  $message  Custom error message (defaults to localized message)
     * @param  int  $code  HTTP status code (defaults to 402 Payment Required)
     * @param  \Throwable|null  $previous  Original SDK exception for debugging
     */
    public function __construct(?string $message = null, int $code = 402, ?\Throwable $previous = null)
    {
        $message = $message ?? __('exceptions.translation.insufficient_funds');
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
            shouldFailover: true
        );
    }
}
