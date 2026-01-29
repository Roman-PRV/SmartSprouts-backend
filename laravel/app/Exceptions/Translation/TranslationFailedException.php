<?php

namespace App\Exceptions\Translation;

use Exception;

class TranslationFailedException extends Exception
{
    public function __construct(?string $message = null, int $code = 0, ?\Throwable $previous = null)
    {
        $message = $message ?? __('exceptions.translation.failed');
        parent::__construct($message, $code, $previous);
    }
}
