<?php

namespace App\Exceptions\Tts;

use Exception;

class InvalidVoiceException extends Exception
{
    public function __construct(
        string $message = 'Invalid voice requested',
        int $code = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
